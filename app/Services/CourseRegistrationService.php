<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Course;
use App\Models\CourseRegistrationSetting;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseRegistrationService
{
    public function __construct(
        protected CourseEligibilityService $eligibilityService,
        protected RegistrationBillingService $billingService
    ) {}

    /**
     * @param  array<int>  $courseIds
     * @return array{success: bool, message: string, rejected_course_ids: array<int>}
     */
    public function save(
        Student $student,
        Year $year,
        Semester $semester,
        array $courseIds
    ): array {
        if (! in_array($semester, [Semester::FIRST, Semester::SECOND], true)) {
            return [
                'success' => false,
                'message' => 'لا يوجد تسجيل مواد في الترم الصيفي.',
                'rejected_course_ids' => $courseIds,
            ];
        }

        if (empty($courseIds)) {
            return [
                'success' => false,
                'message' => 'يرجى اختيار مادة واحدة على الأقل.',
                'rejected_course_ids' => [],
            ];
        }

        $feeGate = $this->billingService->checkFeeGate($student);

        if (! $feeGate['allowed']) {
            return [
                'success' => false,
                'message' => $feeGate['message'],
                'rejected_course_ids' => $courseIds,
            ];
        }

        $defaultGrade = Grade::pendingDefault();

        if (! $defaultGrade) {
            return [
                'success' => false,
                'message' => 'لم يتم تعيين تقييم افتراضي (Pending) في الإعدادات.',
                'rejected_course_ids' => $courseIds,
            ];
        }

        $student->loadMissing(['level', 'section']);
        $courses = Course::query()->with('prerequisites')->whereIn('id', $courseIds)->get();

        if ($courses->count() !== count(array_unique($courseIds))) {
            return [
                'success' => false,
                'message' => 'بعض المواد المختارة غير موجودة.',
                'rejected_course_ids' => $courseIds,
            ];
        }

        $registration = Registration::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'year_id' => $year->id,
                'semester' => $semester->value,
            ]
        );

        if ($registration->wasRecentlyCreated) {
            if (auth('student')->check()) {
                $registration->status = RegistrationStatus::PENDING;
            } else {
                $registration->status = RegistrationStatus::APPROVED;
                if (auth('advisor')->check()) {
                    $registration->created_by_advisor_id = auth('advisor')->id();
                    $registration->approved_by_advisor_id = auth('advisor')->id();
                } elseif (auth('web')->check()) {
                    $registration->created_by_user_id = auth('web')->id();
                    $registration->approved_by_user_id = auth('web')->id();
                }
            }
            $registration->save();
        } elseif (auth('student')->check() && $registration->status !== RegistrationStatus::PENDING) {
            // A student touching their own registration sends it back for review; the
            // charge already taken stays put, so approval only settles the difference.
            $registration->forceFill([
                'status' => RegistrationStatus::PENDING,
                'rejection_reason' => null,
            ])->save();
        }

        $attempts = $this->eligibilityService->getStudentAttempts($student);
        $buckets = $this->eligibilityService->getBuckets($student, $year, $semester, $registration);

        $validCourseIds = [];
        $rejectedCourseIds = [];
        $errors = [];

        foreach ($courses as $course) {
            $validationError = $this->validateCourse(
                $course,
                $student,
                $semester,
                $registration,
                $attempts,
                $buckets
            );

            if ($validationError !== null) {
                $rejectedCourseIds[] = $course->id;
                $errors[] = "{$course->name}: {$validationError}";

                continue;
            }

            $validCourseIds[] = $course->id;
        }

        $optionalValidation = $this->validateOptionalLimit(
            $student,
            $semester,
            $registration,
            $courses->whereIn('id', $validCourseIds)
        );

        if ($optionalValidation['rejected']->isNotEmpty()) {
            foreach ($optionalValidation['rejected'] as $course) {
                $rejectedCourseIds[] = $course->id;
                if (! in_array($optionalValidation['message'], $errors, true)) {
                    $errors[] = $optionalValidation['message'];
                }
            }

            $validCourseIds = array_values(array_diff(
                $validCourseIds,
                $optionalValidation['rejected']->pluck('id')->all()
            ));
        }

        if (empty($validCourseIds)) {
            return [
                'success' => false,
                'message' => implode(' ', $errors) ?: 'لم يتم قبول أي مادة.',
                'rejected_course_ids' => array_values(array_unique($rejectedCourseIds)),
            ];
        }

        $performedBy = $this->resolvePerformedBy();
        $settlement = ['success' => true, 'message' => '', 'delta' => 0.0];

        try {
            DB::transaction(function () use ($registration, $validCourseIds, $defaultGrade, $performedBy, &$settlement) {
                foreach ($validCourseIds as $courseId) {
                    RegistrationCourse::query()->firstOrCreate(
                        [
                            'registration_id' => $registration->id,
                            'course_id' => $courseId,
                        ],
                        [
                            'grade_id' => $defaultGrade->id,
                        ]
                    );
                }

                $settlement = $this->billingService->settleIfApproved($registration, $performedBy);

                if (! $settlement['success']) {
                    throw new InsufficientWalletBalanceException($settlement['message']);
                }
            });
        } catch (InsufficientWalletBalanceException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'rejected_course_ids' => $courseIds,
            ];
        }

        $savedCount = count($validCourseIds);
        $message = "تم حفظ تسجيل {$savedCount} مادة بنجاح.";

        if ($settlement['message'] !== '') {
            $message .= ' '.$settlement['message'];
        }

        if (! empty($errors)) {
            $message .= ' '.implode(' ', $errors);
        }

        return [
            'success' => true,
            'message' => $message,
            'rejected_course_ids' => array_values(array_unique($rejectedCourseIds)),
        ];
    }

    /**
     * The account acting on this registration, used to attribute wallet movements.
     */
    protected function resolvePerformedBy(): ?Model
    {
        return auth('advisor')->user() ?? auth('web')->user() ?? auth('student')->user();
    }

    /**
     * @param  array{
     *     retake: Collection,
     *     improvement: Collection,
     *     due: Collection
     * }  $buckets
     */
    protected function validateCourse(
        Course $course,
        Student $student,
        Semester $semester,
        Registration $registration,
        Collection $attempts,
        array $buckets
    ): ?string {
        if (! $course->is_active) {
            return 'المادة غير مفعّلة.';
        }

        if ($registration->courses()->where('course_id', $course->id)->exists()) {
            return 'المادة مسجّلة بالفعل في هذا الترم.';
        }

        $retakeIds = $buckets['retake']->pluck('id');
        $improvementIds = $buckets['improvement']->pluck('id');
        $dueIds = $buckets['due']->pluck('id');

        if ($retakeIds->contains($course->id)) {
            return null;
        }

        if ($improvementIds->contains($course->id)) {
            if (! $this->eligibilityService->isImprovementBucket($course, $attempts)) {
                return 'المادة غير مؤهّلة للتحسين.';
            }

            return null;
        }

        if ($dueIds->contains($course->id)) {
            if (! $this->eligibilityService->isDueBucket($course, $student, $semester, $attempts)) {
                return 'المادة غير مؤهّلة للتسجيل (متطلبات سابقة أو منهج).';
            }

            return null;
        }

        return 'المادة غير مؤهّلة للتسجيل.';
    }

    /**
     * @param  Collection<int, Course>  $newCourses
     * @return array{accepted: Collection<int, Course>, rejected: Collection<int, Course>, message: string}
     */
    protected function validateOptionalLimit(
        Student $student,
        Semester $semester,
        Registration $registration,
        Collection $newCourses
    ): array {
        $maxOptional = CourseRegistrationSetting::query()
            ->where('level_id', $student->level_id)
            ->where('term_type', $semester->value)
            ->value('max_optional_courses');

        if ($maxOptional === null) {
            return [
                'accepted' => $newCourses,
                'rejected' => collect(),
                'message' => '',
            ];
        }

        $existingOptionalCount = RegistrationCourse::query()
            ->where('registration_id', $registration->id)
            ->whereHas('course', fn ($query) => $query->where('is_selected', true))
            ->count();

        $optionalNewCourses = $newCourses->filter(fn (Course $course) => $course->is_selected)->values();
        $requiredNewCourses = $newCourses->filter(fn (Course $course) => ! $course->is_selected)->values();

        $remainingSlots = max(0, $maxOptional - $existingOptionalCount);
        $acceptedOptional = $optionalNewCourses->take($remainingSlots);
        $rejectedOptional = $optionalNewCourses->slice($remainingSlots)->values();

        $message = $rejectedOptional->isNotEmpty()
            ? "تم الوصول للحد الأقصى من المواد الاختيارية لهذا الترم ({$maxOptional})."
            : '';

        return [
            'accepted' => $requiredNewCourses->merge($acceptedOptional),
            'rejected' => $rejectedOptional,
            'message' => $message,
        ];
    }

    public function getMaxOptionalCourses(Student $student, Semester $semester): ?int
    {
        if (! $student->level_id) {
            return null;
        }

        return CourseRegistrationSetting::query()
            ->where('level_id', $student->level_id)
            ->where('term_type', $semester->value)
            ->value('max_optional_courses');
    }

    public function countSelectedOptional(
        Registration $registration,
        array $selectedCourseIds,
        Collection $allSelectedCourses
    ): int {
        $existingOptionalCount = RegistrationCourse::query()
            ->where('registration_id', $registration->id)
            ->whereHas('course', fn ($query) => $query->where('is_selected', true))
            ->pluck('course_id');

        $newOptionalIds = $allSelectedCourses
            ->filter(fn (Course $course) => $course->is_selected)
            ->pluck('id');

        return $existingOptionalCount
            ->merge($newOptionalIds)
            ->unique()
            ->count();
    }
}
