<?php

namespace App\Services;

use App\Enums\ExamSessionStatus;
use App\Enums\ExamType;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Exceptions\ExamScheduleException;
use App\Models\ExamSession;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\Venue;
use App\Models\Year;
use App\Support\StudentConflict;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns every exam-scheduling business rule (FR-002..FR-010).
 * Livewire components orchestrate; this service decides.
 */
class ExamScheduleService
{
    /**
     * The examinee audience (BR-2 / FR-004): students holding an approved
     * registration for the session's year+semester that includes the course.
     * Soft-deleted students are excluded by the Student model's global scope.
     *
     * @return Collection<int, Student>
     */
    public function examinees(ExamSession $session): Collection
    {
        return Student::query()
            ->whereIn('id', fn ($query) => $this->audienceSubquery($session, $query))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    public function examineeIds(ExamSession $session): array
    {
        return Student::query()
            ->whereIn('id', fn ($query) => $this->audienceSubquery($session, $query))
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    public function examineeCount(ExamSession $session): int
    {
        return $this->courseAudienceCount($session->course_id, (int) $session->year_id, $session->semester);
    }

    /**
     * Examinee count for a course+term without needing a persisted session
     * (live preview on the create form).
     */
    public function courseAudienceCount(int $courseId, int $yearId, Semester $semester): int
    {
        return RegistrationCourse::query()
            ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->whereNull('students.deleted_at')
            ->where('registration_courses.course_id', $courseId)
            ->where('registrations.year_id', $yearId)
            ->where('registrations.semester', $semester->value)
            ->where('registrations.status', RegistrationStatus::APPROVED->value)
            ->count(DB::raw('DISTINCT registrations.student_id'));
    }

    /**
     * Course ids that have at least one approved registration in the term —
     * the exam board's "exam map" (FR-001).
     *
     * @return array<int, int>
     */
    public function audienceCourseIds(int $yearId, Semester $semester): array
    {
        return RegistrationCourse::query()
            ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->whereNull('students.deleted_at')
            ->where('registrations.year_id', $yearId)
            ->where('registrations.semester', $semester->value)
            ->where('registrations.status', RegistrationStatus::APPROVED->value)
            ->distinct()
            ->pluck('registration_courses.course_id')
            ->all();
    }

    /**
     * Examinee count per course for the whole term in ONE grouped query (R4).
     *
     * @return array<int, int> course_id => examinee count
     */
    public function audienceCounts(int $yearId, Semester $semester): array
    {
        return RegistrationCourse::query()
            ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->whereNull('students.deleted_at')
            ->where('registrations.year_id', $yearId)
            ->where('registrations.semester', $semester->value)
            ->where('registrations.status', RegistrationStatus::APPROVED->value)
            ->groupBy('registration_courses.course_id')
            ->select('registration_courses.course_id', DB::raw('COUNT(DISTINCT registrations.student_id) as aggregate'))
            ->pluck('aggregate', 'course_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Students of this session's audience who also sit an overlapping session
     * in the same year+semester — set-based, no N+1 (R4).
     *
     * @return \Illuminate\Support\Collection<int, StudentConflict>
     */
    public function findStudentConflicts(ExamSession $session): \Illuminate\Support\Collection
    {
        $overlapping = $this->overlappingSessions($session);

        if ($overlapping->isEmpty()) {
            return collect();
        }

        $examineeIds = $this->examineeIds($session);

        if ($examineeIds === []) {
            return collect();
        }

        $sessionsByCourse = $overlapping->keyBy('course_id');

        $shared = RegistrationCourse::query()
            ->whereIn('course_id', $overlapping->pluck('course_id')->all())
            ->whereHas('registration', fn ($query) => $query
                ->where('year_id', $session->year_id)
                ->where('semester', $session->semester->value)
                ->where('status', RegistrationStatus::APPROVED->value)
                ->whereIn('student_id', $examineeIds))
            ->with('registration.student:id,name')
            ->get();

        return $shared
            ->map(fn (RegistrationCourse $row) => new StudentConflict(
                $row->registration->student,
                $sessionsByCourse->get($row->course_id),
            ))
            ->filter(fn (StudentConflict $conflict) => $conflict->student !== null && $conflict->other !== null)
            ->values();
    }

    /**
     * Full rule battery (FR-002..FR-006). Throws the first violation found.
     *
     * @param  array<int, int|string>|null  $venueIds  Venues the session is about to occupy;
     *                                                 null ⇒ read from persisted committees.
     */
    public function validateSession(ExamSession $session, ?array $venueIds = null): void
    {
        $this->assertValidTimeRange($session->start_time, $session->end_time);
        $this->assertWithinExamWindow($session);
        $this->assertUniqueCombination($session);

        $overlapping = $this->overlappingSessions($session);

        $this->assertNoVenueConflict($session, $overlapping, $venueIds);
        $this->assertNoStudentConflict($session, $overlapping);
    }

    /**
     * Publish gate (FR-019 / R10): examinees > 0 ∧ capacity holds ∧ seating
     * fresh ∧ zero venue/student conflicts. Re-checked at publish time so a
     * conflict that emerged after saving can never reach students.
     */
    public function assertPublishable(ExamSession $session): void
    {
        $examineeCount = $this->examineeCount($session);

        if ($examineeCount === 0) {
            throw new ExamScheduleException('لا يمكن نشر جلسة لا يوجد بها ممتحنون');
        }

        $totalCapacity = (int) $session->committees()->sum('capacity');

        if ($examineeCount > $totalCapacity) {
            throw new ExamScheduleException(
                "عدد الممتحنين ({$examineeCount}) يتجاوز إجمالي سعة اللجان ({$totalCapacity})"
            );
        }

        if (app(ExamSeatingService::class)->isSeatingStale($session)) {
            throw new ExamScheduleException('التوزيع غير محدّث — أعد توليد التوزيع قبل النشر');
        }

        $overlapping = $this->overlappingSessions($session);

        $this->assertNoVenueConflict($session, $overlapping, null);
        $this->assertNoStudentConflict($session, $overlapping);
    }

    public function publish(ExamSession $session): void
    {
        DB::transaction(function () use ($session) {
            $this->lockYear($session->year_id);

            $this->assertPublishable($session);

            $session->update(['status' => ExamSessionStatus::PUBLISHED]);
        });
    }

    public function unpublish(ExamSession $session): void
    {
        $session->update(['status' => ExamSessionStatus::DRAFT]);
    }

    /**
     * @param  array{course_id: int|string, year_id: int|string, semester: Semester|string, type: ExamType|string, exam_date: string, start_time: string, end_time: string, notes?: string|null}  $attributes
     * @param  array<int, array{id?: int|string, venue_id: int|string, name: string, capacity: int|string}>  $committees
     */
    public function create(array $attributes, array $committees = []): ExamSession
    {
        $session = new ExamSession($this->normalize($attributes));
        $session->status ??= ExamSessionStatus::DRAFT;

        return DB::transaction(function () use ($session, $committees) {
            $this->lockYear($session->year_id);

            $this->validateSession($session, $this->committeeVenueIds($committees));
            $this->assertCommitteesValid($committees);

            $session->save();
            $this->syncCommittees($session, $committees);

            return $session;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{id?: int|string, venue_id: int|string, name: string, capacity: int|string}>|null  $committees  null ⇒ leave committees untouched
     */
    public function update(ExamSession $session, array $attributes, ?array $committees = null): void
    {
        $normalized = $this->normalize($attributes);

        DB::transaction(function () use ($session, $normalized, $committees) {
            $this->lockYear($session->year_id);

            $candidate = clone $session;
            $candidate->fill($normalized);

            $this->validateSession($candidate, $committees === null ? null : $this->committeeVenueIds($committees));

            if ($committees !== null) {
                $this->assertCommitteesValid($committees, $session);
            }

            if ($session->status === ExamSessionStatus::PUBLISHED) {
                $normalized['status'] = ExamSessionStatus::DRAFT;
            }

            $session->fill($normalized)->save();

            if ($committees !== null) {
                $this->syncCommittees($session, $committees);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        if (isset($attributes['semester'])) {
            $attributes['semester'] = $attributes['semester'] instanceof Semester
                ? $attributes['semester']->value
                : $attributes['semester'];
        }

        if (isset($attributes['type'])) {
            $attributes['type'] = $attributes['type'] instanceof ExamType
                ? $attributes['type']->value
                : $attributes['type'];
        }

        foreach (['start_time', 'end_time'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = $this->padTime($attributes[$field]);
            }
        }

        return $attributes;
    }

    /**
     * Builds the audience sub-select (approved registrations of the term
     * including the course) inside the given whereIn closure.
     */
    private function audienceSubquery(ExamSession $session, $query): void
    {
        $query->select('registrations.student_id')
            ->from('registrations')
            ->join('registration_courses', 'registration_courses.registration_id', '=', 'registrations.id')
            ->where('registration_courses.course_id', $session->course_id)
            ->where('registrations.year_id', $session->year_id)
            ->where('registrations.semester', $session->semester->value)
            ->where('registrations.status', RegistrationStatus::APPROVED->value);
    }

    /**
     * Same year+semester+date with intersecting times, excluding self on edit (R2).
     *
     * @return Collection<int, ExamSession>
     */
    private function overlappingSessions(ExamSession $session): Collection
    {
        return ExamSession::query()
            ->where('year_id', $session->year_id)
            ->where('semester', $session->semester->value)
            ->whereDate('exam_date', $session->exam_date instanceof \DateTimeInterface
                ? $session->exam_date->format('Y-m-d')
                : $session->exam_date)
            ->where('start_time', '<', $this->padTime($session->end_time))
            ->where('end_time', '>', $this->padTime($session->start_time))
            ->when($session->exists, fn ($query) => $query->whereKeyNot($session->getKey()))
            ->with('course:id,name')
            ->get();
    }

    private function assertValidTimeRange(string $start, string $end): void
    {
        foreach (['start_time' => $start, 'end_time' => $end] as $label => $time) {
            if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $time, $m) !== 1) {
                throw new ExamScheduleException('تنسيق الوقت يجب أن يكون HH:MM');
            }

            if (((int) $m[2]) % 15 !== 0) {
                throw new ExamScheduleException(
                    'أوقات البداية والنهاية يجب أن تكون بمضاعفات ربع ساعة (15 دقيقة)'
                );
            }
        }

        if ($this->timeToMinutes($end) <= $this->timeToMinutes($start)) {
            throw new ExamScheduleException('يجب أن يكون وقت النهاية أكبر من وقت البداية');
        }
    }

    private function assertWithinExamWindow(ExamSession $session): void
    {
        $year = $session->year ?? Year::find($session->year_id);

        $window = $year?->semesterExamWindow($session->semester);

        if ($window === null) {
            return;
        }

        $date = $session->exam_date instanceof \DateTimeInterface
            ? $session->exam_date->format('Y-m-d')
            : (string) $session->exam_date;

        if ($date < $window['from'] || $date > $window['to']) {
            throw new ExamScheduleException(
                "تاريخ الامتحان يجب أن يكون داخل فترة امتحانات الترم ({$window['from']} إلى {$window['to']})"
            );
        }
    }

    private function assertUniqueCombination(ExamSession $session): void
    {
        $exists = ExamSession::query()
            ->where('course_id', $session->course_id)
            ->where('year_id', $session->year_id)
            ->where('semester', $session->semester->value)
            ->where('type', $session->type->value)
            ->when($session->exists, fn ($query) => $query->whereKeyNot($session->getKey()))
            ->exists();

        if ($exists) {
            throw new ExamScheduleException(
                'يوجد جلسة امتحان لهذه المادة في نفس الترم ونفس النوع بالفعل'
            );
        }
    }

    /**
     * @param  Collection<int, ExamSession>  $overlapping
     * @param  array<int, int|string>|null  $venueIds
     */
    private function assertNoVenueConflict(ExamSession $session, Collection $overlapping, ?array $venueIds): void
    {
        if ($venueIds === null) {
            $venueIds = $session->exists
                ? $session->committees()->pluck('venue_id')->all()
                : [];
        }

        $venueIds = array_values(array_unique(array_map('intval', $venueIds)));

        if ($venueIds === [] || $overlapping->isEmpty()) {
            return;
        }

        $overlapping->loadMissing('committees');

        $conflict = $overlapping->first(
            fn (ExamSession $other) => $other->committees->pluck('venue_id')->intersect($venueIds)->isNotEmpty(),
        );

        if ($conflict !== null) {
            $conflict->loadMissing('committees.venue');
            $clashingVenueId = $conflict->committees->pluck('venue_id')->intersect($venueIds)->first();
            $venueName = $conflict->committees->firstWhere('venue_id', $clashingVenueId)?->venue->name ?? 'غير معروف';

            throw new ExamScheduleException(
                "لا يمكن الحفظ: المكان \"{$venueName}\" محجوز يوم ".$this->dateString($session)
                .' من '.substr((string) $conflict->start_time, 0, 5).' إلى '.substr((string) $conflict->end_time, 0, 5)
                ." للمادة \"{$conflict->course->name}\""
            );
        }
    }

    /**
     * @param  Collection<int, ExamSession>  $overlapping
     */
    private function assertNoStudentConflict(ExamSession $session, Collection $overlapping): void
    {
        if ($overlapping->isEmpty()) {
            return;
        }

        $conflicts = $this->findStudentConflicts($session);

        if ($conflicts->isEmpty()) {
            return;
        }

        $currentCourseName = $session->course?->name ?? '';

        $names = $conflicts->take(10)
            ->map(fn (StudentConflict $c) => "الطالب \"{$c->student->name}\" ({$currentCourseName} × {$c->other->course->name})")
            ->implode('، ');

        $more = $conflicts->count() > 10 ? '، و'.($conflicts->count() - 10).' طالبًا آخر' : '';

        throw new ExamScheduleException(
            "لا يمكن الحفظ: يوجد تعارض في مواعيد الامتحانات — {$names}{$more}"
        );
    }

    /**
     * @param  array<int, array{id?: int|string, venue_id: int|string, name: string, capacity: int|string}>  $committees
     */
    private function assertCommitteesValid(array $committees, ?ExamSession $session = null): void
    {
        $names = [];

        foreach ($committees as $committee) {
            if (empty($committee['name']) || trim((string) $committee['name']) === '') {
                throw new ExamScheduleException('اسم اللجنة مطلوب لكل لجنة');
            }

            $name = trim((string) $committee['name']);

            if (in_array($name, $names, true)) {
                throw new ExamScheduleException("اسم اللجنة \"{$name}\" مكرر داخل نفس الجلسة");
            }

            $names[] = $name;

            if ((int) ($committee['capacity'] ?? 0) < 1) {
                throw new ExamScheduleException("سعة اللجنة \"{$name}\" يجب أن تكون رقمًا موجبًا");
            }

            $venue = Venue::find($committee['venue_id'] ?? null);

            if ($venue === null) {
                throw new ExamScheduleException('المكان المختار للجنة غير موجود');
            }

            if (! $venue->is_active && (! $session?->exists
                || ! $session->committees()->where('venue_id', $venue->id)->where('id', $committee['id'] ?? 0)->exists())) {
                throw new ExamScheduleException("لا يمكن اختيار مكان غير مفعّل ({$venue->name}) للجان جديدة");
            }
        }
    }

    /**
     * @param  array<int, array{id?: int|string, venue_id: int|string, name: string, capacity: int|string}>  $committees
     */
    private function syncCommittees(ExamSession $session, array $committees): void
    {
        $existing = $session->committees()->get()->keyBy('id');
        $keepIds = [];

        foreach ($committees as $data) {
            $payload = [
                'venue_id' => (int) $data['venue_id'],
                'name' => trim((string) $data['name']),
                'capacity' => (int) $data['capacity'],
            ];

            $committee = isset($data['id']) ? $existing->get((int) $data['id']) : null;

            if ($committee !== null) {
                $committee->update($payload);
            } else {
                $committee = $session->committees()->create($payload);
            }

            $keepIds[] = $committee->id;
        }

        $session->committees()
            ->whereNotIn('id', $keepIds ?: [0])
            ->get()
            ->each(function ($committee) {
                if ($committee->assignments()->exists()) {
                    throw new ExamScheduleException(
                        "لا يمكن حذف اللجنة \"{$committee->name}\" لوجود ممتحنين بها — أعد توليد التوزيع أولًا"
                    );
                }

                $committee->delete();
            });
    }

    /**
     * @param  array<int, array{venue_id?: int|string}>  $committees
     * @return array<int, int>
     */
    private function committeeVenueIds(array $committees): array
    {
        return array_values(array_filter(array_map(
            fn ($committee) => isset($committee['venue_id']) ? (int) $committee['venue_id'] : null,
            $committees,
        )));
    }

    /**
     * Single serialization point per academic year (R5): every exam save
     * locks the year row before running conflict/uniqueness checks.
     */
    private function lockYear(int|string $yearId): void
    {
        Year::whereKey($yearId)->lockForUpdate()->first();
    }

    private function dateString(ExamSession $session): string
    {
        return $session->exam_date instanceof \DateTimeInterface
            ? $session->exam_date->format('Y-m-d')
            : (string) $session->exam_date;
    }

    private function padTime(string $time): string
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 3, '00');

        return str_pad($hours, 2, '0', STR_PAD_LEFT).':'.$minutes.':'.str_pad(explode(':', $time)[2] ?? '00', 2, '0', STR_PAD_LEFT);
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $this->padTime($time));

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
