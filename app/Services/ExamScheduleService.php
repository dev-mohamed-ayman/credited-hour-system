<?php

namespace App\Services;

use App\Enums\ExamSessionStatus;
use App\Enums\ExamType;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Exceptions\ExamScheduleException;
use App\Models\Course;
use App\Models\ExamCommittee;
use App\Models\ExamCommitteeStudent;
use App\Models\ExamSession;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\Venue;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns every exam-scheduling business rule. A committee is term-wide
 * (year + semester), holds hand-picked students (by code) and carries its
 * own exam timetable. A student sits a committee exam when they are a
 * member of that committee AND hold an approved registration including
 * the exam's course in the committee's term.
 */
class ExamScheduleService
{
    /**
     * @param  array{year_id: int|string, semester: Semester|string, venue_id: int|string, name: string, capacity: int|string, notes?: string|null}  $attributes
     */
    public function createCommittee(array $attributes): ExamCommittee
    {
        $committee = new ExamCommittee($this->normalizeCommittee($attributes));
        $committee->status = ExamSessionStatus::DRAFT;

        return DB::transaction(function () use ($committee) {
            $this->lockYear($committee->year_id);

            $this->assertCommitteeValid($committee);
            $committee->save();

            return $committee;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCommittee(ExamCommittee $committee, array $attributes): void
    {
        $normalized = $this->normalizeCommittee($attributes);
        unset($normalized['year_id'], $normalized['semester']);

        DB::transaction(function () use ($committee, $normalized) {
            $this->lockYear($committee->year_id);

            $candidate = clone $committee;
            $candidate->fill($normalized);

            $this->assertCommitteeValid($candidate);

            if ($candidate->venue_id !== $committee->getOriginal('venue_id')) {
                foreach ($committee->sessions()->with('course:id,name')->get() as $session) {
                    $this->assertNoVenueConflict($session, (int) $candidate->venue_id);
                }
            }

            $committee->fill($normalized)->save();
            $this->markDraft($committee);
        });
    }

    public function deleteCommittee(ExamCommittee $committee): void
    {
        DB::transaction(function () use ($committee) {
            $committee->sessions()->delete();
            $committee->members()->delete();
            $committee->delete();
        });
    }

    /**
     * Adds students to the committee by their codes (username). Valid codes
     * are added even when others in the same batch are rejected.
     *
     * @param  array<int, string>  $codes
     * @return array{added: array<int, string>, errors: array<string, string>}
     */
    public function addStudentsByCodes(ExamCommittee $committee, array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code) => trim((string) $code),
            $codes,
        ), fn (string $code) => $code !== '')));

        if ($codes === []) {
            return ['added' => [], 'errors' => []];
        }

        return DB::transaction(function () use ($committee, $codes) {
            $this->lockYear($committee->year_id);

            $students = Student::query()
                ->whereIn(DB::raw('LOWER(username)'), array_map('mb_strtolower', $codes))
                ->get()
                ->keyBy(fn (Student $student) => mb_strtolower($student->username));

            $memberships = ExamCommitteeStudent::query()
                ->whereIn('student_id', $students->pluck('id')->all() ?: [0])
                ->whereHas('committee', fn ($query) => $query
                    ->where('year_id', $committee->year_id)
                    ->where('semester', $committee->semester->value))
                ->with('committee:id,name')
                ->get()
                ->keyBy('student_id');

            $sessions = $committee->sessions()->with('course:id,name')->get();
            $remaining = $committee->capacity - $committee->members()->count();
            $nextSeat = ((int) $committee->members()->max('seat_number')) + 1;

            $added = [];
            $errors = [];

            foreach ($codes as $code) {
                $student = $students->get(mb_strtolower($code));

                if ($student === null) {
                    $errors[$code] = 'لا يوجد طالب بهذا الكود';

                    continue;
                }

                $membership = $memberships->get($student->id);

                if ($membership !== null) {
                    $errors[$code] = $membership->exam_committee_id === $committee->id
                        ? 'الطالب مضاف بالفعل لهذه اللجنة'
                        : "الطالب مضاف بالفعل إلى \"{$membership->committee->name}\" في نفس الترم";

                    continue;
                }

                if ($remaining < 1) {
                    $errors[$code] = "اللجنة ممتلئة ({$committee->capacity} طالب)";

                    continue;
                }

                if ($clash = $this->studentClashInSessions($student, $committee, $sessions)) {
                    $errors[$code] = $clash;

                    continue;
                }

                $committee->members()->create([
                    'student_id' => $student->id,
                    'seat_number' => $nextSeat++,
                ]);

                $remaining--;
                $added[] = $student->username;
            }

            if ($added !== []) {
                $this->markDraft($committee);
            }

            return ['added' => $added, 'errors' => $errors];
        });
    }

    public function removeStudent(ExamCommittee $committee, int $studentId): void
    {
        DB::transaction(function () use ($committee, $studentId) {
            $committee->members()->where('student_id', $studentId)->delete();

            $this->markDraft($committee);
        });
    }

    /**
     * @param  array{course_id: int|string, type: ExamType|string, exam_date: string, start_time: string, end_time: string, notes?: string|null}  $attributes
     */
    public function createSession(ExamCommittee $committee, array $attributes): ExamSession
    {
        $session = new ExamSession($this->normalizeSession($attributes));
        $session->exam_committee_id = $committee->id;
        $session->setRelation('committee', $committee);

        return DB::transaction(function () use ($committee, $session) {
            $this->lockYear($committee->year_id);

            $this->validateSession($session);
            $session->save();

            $this->markDraft($committee);

            return $session;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSession(ExamSession $session, array $attributes): void
    {
        $normalized = $this->normalizeSession($attributes);

        DB::transaction(function () use ($session, $normalized) {
            $committee = $session->committee;
            $this->lockYear($committee->year_id);

            $candidate = clone $session;
            $candidate->fill($normalized);
            $candidate->setRelation('committee', $committee);

            $this->validateSession($candidate);
            $session->fill($normalized)->save();

            $this->markDraft($committee);
        });
    }

    public function deleteSession(ExamSession $session): void
    {
        DB::transaction(function () use ($session) {
            $committee = $session->committee;
            $session->delete();

            $this->markDraft($committee);
        });
    }

    /**
     * Full rule battery for a single exam inside its committee.
     */
    public function validateSession(ExamSession $session): void
    {
        $committee = $session->committee;

        $this->assertValidTimeRange((string) $session->start_time, (string) $session->end_time);
        $this->assertWithinExamWindow($committee, $session);
        $this->assertUniqueCourseInCommittee($session);

        if ($this->examineeCount($session) === 0) {
            throw new ExamScheduleException('لا يوجد طلاب في هذه اللجنة مسجلون (تسجيل معتمد) في هذه المادة');
        }

        $this->assertNoVenueConflict($session, (int) $committee->venue_id);
        $this->assertNoStudentConflict($session);
    }

    /**
     * Publish gate: members ≤ capacity, at least one exam, and zero venue /
     * student conflicts — re-checked at publish time so a conflict that
     * emerged after saving never reaches students.
     */
    public function assertPublishable(ExamCommittee $committee): void
    {
        $memberCount = $committee->members()->count();

        if ($memberCount === 0) {
            throw new ExamScheduleException('لا يمكن نشر لجنة لا يوجد بها طلاب');
        }

        if ($memberCount > $committee->capacity) {
            throw new ExamScheduleException(
                "عدد طلاب اللجنة ({$memberCount}) يتجاوز سعتها ({$committee->capacity})"
            );
        }

        $sessions = $committee->sessions()->with('course:id,name')->get();

        if ($sessions->isEmpty()) {
            throw new ExamScheduleException('لا يمكن نشر لجنة بدون مواعيد امتحانات');
        }

        foreach ($sessions as $session) {
            $session->setRelation('committee', $committee);
            $this->assertNoVenueConflict($session, (int) $committee->venue_id);
            $this->assertNoStudentConflict($session);
        }
    }

    public function publish(ExamCommittee $committee): void
    {
        DB::transaction(function () use ($committee) {
            $this->lockYear($committee->year_id);

            $this->assertPublishable($committee);

            $committee->update(['status' => ExamSessionStatus::PUBLISHED]);
        });
    }

    public function unpublish(ExamCommittee $committee): void
    {
        $committee->update(['status' => ExamSessionStatus::DRAFT]);
    }

    /**
     * Committee members sitting this exam (approved registration for the
     * course in the committee's term), ordered by seat number.
     *
     * @return Collection<int, ExamCommitteeStudent>
     */
    public function examinees(ExamSession $session): Collection
    {
        return $this->examineeMembersQuery($session->committee, (int) $session->course_id)
            ->with(['student:id,name,username,section_id', 'student.section:id,name'])
            ->orderBy('seat_number')
            ->get();
    }

    public function examineeCount(ExamSession $session): int
    {
        return $this->examineeMembersQuery($session->committee, (int) $session->course_id)->count();
    }

    /**
     * Courses that the committee's members are registered in (approved) for
     * the term, with the number of members sitting each — one grouped query.
     *
     * @return array<int, int> course_id => examinee count
     */
    public function courseCounts(ExamCommittee $committee): array
    {
        return $this->approvedRegistrationCourses($committee->year_id, $committee->semester)
            ->join('exam_committee_students', 'exam_committee_students.student_id', '=', 'registrations.student_id')
            ->where('exam_committee_students.exam_committee_id', $committee->id)
            ->groupBy('registration_courses.course_id')
            ->select('registration_courses.course_id', DB::raw('COUNT(DISTINCT registrations.student_id) as aggregate'))
            ->pluck('aggregate', 'course_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Approved course ids per member of the committee for the term.
     *
     * @return array<int, array<int, int>> student_id => course ids
     */
    public function memberCourseIds(ExamCommittee $committee): array
    {
        return $this->approvedRegistrationCourses($committee->year_id, $committee->semester)
            ->join('exam_committee_students', 'exam_committee_students.student_id', '=', 'registrations.student_id')
            ->where('exam_committee_students.exam_committee_id', $committee->id)
            ->select('registrations.student_id', 'registration_courses.course_id')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('course_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();
    }

    /**
     * Students holding an approved registration in the term who are not a
     * member of any committee of that term.
     *
     * @return Builder<Student>
     */
    public function unassignedStudentsQuery(int $yearId, Semester $semester): Builder
    {
        return Student::query()
            ->whereIn('id', fn ($query) => $query->select('registrations.student_id')
                ->from('registrations')
                ->join('registration_courses', 'registration_courses.registration_id', '=', 'registrations.id')
                ->where('registrations.year_id', $yearId)
                ->where('registrations.semester', $semester->value)
                ->where('registrations.status', RegistrationStatus::APPROVED->value))
            ->whereNotIn('id', fn ($query) => $query->select('exam_committee_students.student_id')
                ->from('exam_committee_students')
                ->join('exam_committees', 'exam_committees.id', '=', 'exam_committee_students.exam_committee_id')
                ->where('exam_committees.year_id', $yearId)
                ->where('exam_committees.semester', $semester->value));
    }

    /**
     * Published exams a student sits in their committee for the term, each
     * with the committee (and venue) and the student's seat number.
     *
     * @return array{membership: ExamCommitteeStudent|null, sessions: \Illuminate\Support\Collection<int, ExamSession>, unscheduled: \Illuminate\Support\Collection<int, Course>}
     */
    public function studentTimetable(Student $student, Year $year, Semester $semester): array
    {
        $courseIds = $this->approvedRegistrationCourses($year->id, $semester)
            ->where('registrations.student_id', $student->id)
            ->distinct()
            ->pluck('registration_courses.course_id')
            ->map(fn ($id) => (int) $id);

        $membership = ExamCommitteeStudent::query()
            ->where('student_id', $student->id)
            ->whereHas('committee', fn ($query) => $query
                ->where('year_id', $year->id)
                ->where('semester', $semester->value)
                ->where('status', ExamSessionStatus::PUBLISHED->value))
            ->with('committee.venue:id,name')
            ->first();

        if ($membership === null) {
            return ['membership' => null, 'sessions' => collect(), 'unscheduled' => collect()];
        }

        $sessions = $membership->committee->sessions()
            ->with('course:id,name')
            ->whereIn('course_id', $courseIds->all())
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $unscheduled = Course::query()
            ->whereIn('id', $courseIds->diff($sessions->pluck('course_id'))->all())
            ->orderBy('name')
            ->get();

        return ['membership' => $membership, 'sessions' => $sessions, 'unscheduled' => $unscheduled];
    }

    /**
     * Base join: approved registration courses of non-deleted students in a term.
     */
    private function approvedRegistrationCourses(int|string $yearId, Semester $semester): Builder
    {
        return RegistrationCourse::query()
            ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->whereNull('students.deleted_at')
            ->where('registrations.year_id', $yearId)
            ->where('registrations.semester', $semester->value)
            ->where('registrations.status', RegistrationStatus::APPROVED->value);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ExamCommitteeStudent>
     */
    private function examineeMembersQuery(ExamCommittee $committee, int $courseId): Builder
    {
        return $committee->members()
            ->whereIn('student_id', fn ($query) => $query->select('registrations.student_id')
                ->from('registrations')
                ->join('registration_courses', 'registration_courses.registration_id', '=', 'registrations.id')
                ->where('registration_courses.course_id', $courseId)
                ->where('registrations.year_id', $committee->year_id)
                ->where('registrations.semester', $committee->semester->value)
                ->where('registrations.status', RegistrationStatus::APPROVED->value))
            ->getQuery();
    }

    /**
     * A committee's own exams that overlap the given one (same date,
     * intersecting times), excluding itself.
     *
     * @return Collection<int, ExamSession>
     */
    private function overlappingSessionsInCommittee(ExamSession $session): Collection
    {
        return $this->overlappingQuery($session)
            ->where('exam_committee_id', $session->exam_committee_id)
            ->with('course:id,name')
            ->get();
    }

    private function overlappingQuery(ExamSession $session): Builder
    {
        return ExamSession::query()
            ->whereDate('exam_date', $this->dateString($session))
            ->where('start_time', '<', $this->padTime((string) $session->end_time))
            ->where('end_time', '>', $this->padTime((string) $session->start_time))
            ->when($session->exists, fn ($query) => $query->whereKeyNot($session->getKey()));
    }

    /**
     * Another committee in the same room at an overlapping time.
     */
    private function assertNoVenueConflict(ExamSession $session, int $venueId): void
    {
        $conflict = $this->overlappingQuery($session)
            ->where('exam_committee_id', '!=', $session->exam_committee_id)
            ->whereHas('committee', fn ($query) => $query->where('venue_id', $venueId))
            ->with(['course:id,name', 'committee:id,name,venue_id', 'committee.venue:id,name'])
            ->first();

        if ($conflict !== null) {
            throw new ExamScheduleException(
                "لا يمكن الحفظ: المكان \"{$conflict->committee->venue->name}\" محجوز يوم ".$this->dateString($session)
                .' من '.substr((string) $conflict->start_time, 0, 5).' إلى '.substr((string) $conflict->end_time, 0, 5)
                ." لـ\"{$conflict->committee->name}\" (مادة \"{$conflict->course->name}\")"
            );
        }
    }

    /**
     * A committee member registered in this exam's course AND in another
     * overlapping exam of the same committee.
     */
    private function assertNoStudentConflict(ExamSession $session): void
    {
        $overlapping = $this->overlappingSessionsInCommittee($session);

        if ($overlapping->isEmpty()) {
            return;
        }

        $committee = $session->committee;
        $courseIdsByStudent = $this->memberCourseIds($committee);
        $currentCourseId = (int) $session->course_id;
        $currentCourseName = $session->course?->name ?? Course::find($currentCourseId)?->name ?? '';

        $clashes = [];

        foreach ($courseIdsByStudent as $studentId => $courseIds) {
            if (! in_array($currentCourseId, $courseIds, true)) {
                continue;
            }

            $other = $overlapping->first(fn (ExamSession $candidate) => in_array((int) $candidate->course_id, $courseIds, true));

            if ($other !== null) {
                $clashes[$studentId] = $other;
            }
        }

        if ($clashes === []) {
            return;
        }

        $names = Student::query()->whereIn('id', array_keys($clashes))->pluck('name', 'id');

        $list = collect($clashes)->take(10)
            ->map(fn (ExamSession $other, $studentId) => "الطالب \"{$names[$studentId]}\" ({$currentCourseName} × {$other->course->name})")
            ->implode('، ');

        $more = count($clashes) > 10 ? '، و'.(count($clashes) - 10).' طالبًا آخر' : '';

        throw new ExamScheduleException(
            "لا يمكن الحفظ: يوجد تعارض في مواعيد الامتحانات — {$list}{$more}"
        );
    }

    /**
     * Would adding this student to the committee make them sit two
     * overlapping exams? Returns the error message, or null when clean.
     *
     * @param  Collection<int, ExamSession>  $sessions
     */
    private function studentClashInSessions(Student $student, ExamCommittee $committee, Collection $sessions): ?string
    {
        if ($sessions->count() < 2) {
            return null;
        }

        $courseIds = $this->approvedRegistrationCourses($committee->year_id, $committee->semester)
            ->where('registrations.student_id', $student->id)
            ->pluck('registration_courses.course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sitting = $sessions->filter(fn (ExamSession $session) => in_array((int) $session->course_id, $courseIds, true))->values();

        foreach ($sitting as $index => $first) {
            foreach ($sitting->slice($index + 1) as $second) {
                if ($first->overlaps($second)) {
                    return "تعارض في مواعيد امتحانات الطالب ({$first->course->name} × {$second->course->name})";
                }
            }
        }

        return null;
    }

    private function assertCommitteeValid(ExamCommittee $committee): void
    {
        $name = trim((string) $committee->name);

        if ($name === '') {
            throw new ExamScheduleException('اسم اللجنة مطلوب');
        }

        $duplicate = ExamCommittee::query()
            ->where('year_id', $committee->year_id)
            ->where('semester', $committee->semester->value)
            ->where('name', $name)
            ->when($committee->exists, fn ($query) => $query->whereKeyNot($committee->getKey()))
            ->exists();

        if ($duplicate) {
            throw new ExamScheduleException("يوجد لجنة باسم \"{$name}\" في نفس الترم بالفعل");
        }

        if ((int) $committee->capacity < 1) {
            throw new ExamScheduleException('سعة اللجنة يجب أن تكون رقمًا موجبًا');
        }

        if ($committee->exists) {
            $memberCount = $committee->members()->count();

            if ((int) $committee->capacity < $memberCount) {
                throw new ExamScheduleException(
                    "لا يمكن تقليل السعة إلى {$committee->capacity} — اللجنة بها {$memberCount} طالب"
                );
            }
        }

        $venue = Venue::find($committee->venue_id);

        if ($venue === null) {
            throw new ExamScheduleException('المكان المختار للجنة غير موجود');
        }

        $keepsInactiveVenue = $committee->exists && (int) $committee->getOriginal('venue_id') === $venue->id;

        if (! $venue->is_active && ! $keepsInactiveVenue) {
            throw new ExamScheduleException("لا يمكن اختيار مكان غير مفعّل ({$venue->name})");
        }
    }

    private function assertUniqueCourseInCommittee(ExamSession $session): void
    {
        $exists = ExamSession::query()
            ->where('exam_committee_id', $session->exam_committee_id)
            ->where('course_id', $session->course_id)
            ->where('type', $session->type->value)
            ->when($session->exists, fn ($query) => $query->whereKeyNot($session->getKey()))
            ->exists();

        if ($exists) {
            throw new ExamScheduleException('هذه المادة لها ميعاد امتحان من نفس النوع في هذه اللجنة بالفعل');
        }
    }

    private function assertValidTimeRange(string $start, string $end): void
    {
        foreach ([$start, $end] as $time) {
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

    private function assertWithinExamWindow(ExamCommittee $committee, ExamSession $session): void
    {
        $window = ($committee->year ?? Year::find($committee->year_id))?->semesterExamWindow($committee->semester);

        if ($window === null) {
            return;
        }

        $date = $this->dateString($session);

        if ($date < $window['from'] || $date > $window['to']) {
            throw new ExamScheduleException(
                "تاريخ الامتحان يجب أن يكون داخل فترة امتحانات الترم ({$window['from']} إلى {$window['to']})"
            );
        }
    }

    /**
     * Any edit to a published committee sends it back to draft.
     */
    private function markDraft(ExamCommittee $committee): void
    {
        ExamCommittee::whereKey($committee->getKey())->update(['status' => ExamSessionStatus::DRAFT->value]);

        $committee->status = ExamSessionStatus::DRAFT;
        $committee->syncOriginalAttribute('status');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeCommittee(array $attributes): array
    {
        if (isset($attributes['semester']) && $attributes['semester'] instanceof Semester) {
            $attributes['semester'] = $attributes['semester']->value;
        }

        if (array_key_exists('name', $attributes)) {
            $attributes['name'] = trim((string) $attributes['name']);
        }

        foreach (['venue_id', 'capacity', 'year_id'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = (int) $attributes[$field];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeSession(array $attributes): array
    {
        if (isset($attributes['type']) && $attributes['type'] instanceof ExamType) {
            $attributes['type'] = $attributes['type']->value;
        }

        if (isset($attributes['course_id'])) {
            $attributes['course_id'] = (int) $attributes['course_id'];
        }

        foreach (['start_time', 'end_time'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = $this->padTime((string) $attributes[$field]);
            }
        }

        return $attributes;
    }

    /**
     * Single serialization point per academic year: every exam save locks
     * the year row before running conflict/uniqueness checks.
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
        $parts = explode(':', $time);

        return str_pad($parts[0], 2, '0', STR_PAD_LEFT).':'
            .str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT).':'
            .str_pad($parts[2] ?? '00', 2, '0', STR_PAD_LEFT);
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $this->padTime($time));

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
