<?php

namespace App\Services;

use App\Enums\ExamResultStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Models\Course;
use App\Models\ExamCommitteeStudent;
use App\Models\Grade;
use App\Models\RegistrationCourse;
use App\Models\User;
use App\Models\Year;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

/**
 * Reads a course's control sheet (كود الطالب، رقم الجلوس، التحريري، اعمال السنة،
 * المجموع، التقدير، ملاحظة), checks every row against the enrollment, the exam
 * seats and the grade scale, and only then writes the results. A sheet with a
 * single bad row is rejected as a whole, so a course is never half-uploaded.
 */
class ResultUploadService
{
    public const WRITTEN_MAX = 50;

    public const COURSEWORK_MAX = 50;

    public const TOTAL_MAX = 100;

    /**
     * Columns added to the exported sheet for the reader only.
     *
     * @var array<int, string>
     */
    public const INFO_HEADERS = ['اسم الطالب', 'الفرقة', 'التخصص', 'حالة التسجيل'];

    /**
     * Columns the upload reads, located by header (any order), with their accepted spellings.
     * Any other column, such as the student name in the exported sheet, is ignored.
     *
     * @var array<string, array<int, string>>
     */
    public const HEADERS = [
        'code' => ['كود الطالب'],
        'seat' => ['رقم الجلوس'],
        'written' => ['التحريري', 'التحريرى'],
        'coursework' => ['اعمال السنة', 'اعمال السنه'],
        'total' => ['المجموع'],
        'grade' => ['التقدير'],
        'note' => ['ملاحظة', 'ملاحظه'],
    ];

    /**
     * Why results cannot be uploaded for the term right now, or null when they can.
     */
    public function termBlockReason(Year $year, Semester $semester): ?string
    {
        if ($year->getSemesterStatus($semester) === SemesterStatus::OPEN_REGISTRATION) {
            return 'التسجيل مفتوح في هذا الترم، يجب غلق التسجيل أولاً قبل رفع النتيجة.';
        }

        return null;
    }

    /**
     * @return array<int, array<int, mixed>>
     *
     * @throws \RuntimeException when the file cannot be read as a spreadsheet
     */
    public function readSheet(string $path, string $extension): array
    {
        $reader = strtolower($extension) === 'csv' ? new CsvReader : new XlsxReader;

        try {
            $reader->open($path);
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    /** @var Row $row */
                    $rows[] = $row->toArray();
                }

                break;
            }

            $reader->close();
        } catch (Throwable $exception) {
            throw new \RuntimeException('تعذر قراءة الملف، تأكد أنه ملف Excel (xlsx) أو CSV صالح.', previous: $exception);
        }

        return $rows;
    }

    /**
     * Validates the sheet rows for one course in one term without writing anything.
     * Rows left without marks, grade and note are treated as not entered yet.
     *
     * @param  array<int, array<int, mixed>>  $sheetRows  raw rows, header first
     * @return array{
     *     errors: array<int, array{row: int, message: string}>,
     *     rows: array<int, array{registration_course_id: int, row: int, code: string, name: string, written_score: float|null, coursework_score: float|null, total_score: float|null, exam_status: string|null, grade_id: int, grade_name: string, note: string|null, previous_grade: string|null}>,
     *     missing: array<int, array{code: string, name: string}>,
     *     overwritten: int
     * }
     */
    public function analyze(array $sheetRows, Course $course, Year $year, Semester $semester): array
    {
        $result = ['errors' => [], 'rows' => [], 'missing' => [], 'overwritten' => 0];

        [$columns, $headerError] = $this->resolveColumns($sheetRows[0] ?? []);

        if ($headerError) {
            $result['errors'][] = ['row' => 1, 'message' => $headerError];

            return $result;
        }

        $enrollments = $this->enrollments($course, $year, $semester);
        $seats = $this->seatNumbers($year, $semester);
        $grades = Grade::query()->get();
        $gradesByName = $grades->keyBy(fn (Grade $grade) => mb_strtoupper(trim($grade->name)));
        $scale = $grades->filter(fn (Grade $grade) => $grade->isScored())->sortByDesc(fn (Grade $grade) => (float) $grade->min_score);

        $seenCodes = [];

        foreach (array_slice($sheetRows, 1, preserve_keys: true) as $index => $cells) {
            $rowNumber = $index + 1;
            $cells = array_map(fn (int $position) => $this->normalizeCell($cells[$position] ?? null), $columns);
            ['code' => $code, 'seat' => $seat, 'written' => $written, 'coursework' => $coursework, 'total' => $total, 'grade' => $gradeName, 'note' => $note] = $cells;

            if ($written === null && $coursework === null && $total === null && $gradeName === null && $note === null) {
                continue;
            }
            $code = $code === null ? null : mb_strtoupper($this->asIdentifier($code));
            $rowErrors = [];

            if ($code === null) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => 'كود الطالب فارغ.'];

                continue;
            }

            if (isset($seenCodes[$code])) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => "كود الطالب {$code} مكرر (ورد من قبل في السطر {$seenCodes[$code]})."];

                continue;
            }

            $seenCodes[$code] = $rowNumber;
            $enrollment = $enrollments->get($code);

            if (! $enrollment) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => "الطالب {$code} غير مسجل في هذه المادة في الترم المحدد."];

                continue;
            }

            if ($enrollment->registration->status !== RegistrationStatus::APPROVED) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => "تسجيل الطالب {$code} لم يتم اعتماده بعد."];

                continue;
            }

            $seatError = $this->seatError($seats->get($enrollment->registration->student_id, []), $seat);

            if ($seatError) {
                $rowErrors[] = $seatError;
            }

            $outcome = $this->evaluateMarks($written, $coursework, $total, $gradeName, $gradesByName, $scale, $rowErrors);

            if ($note !== null && mb_strlen((string) $note) > 255) {
                $rowErrors[] = 'الملاحظة أطول من 255 حرفاً.';
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $message) {
                    $result['errors'][] = ['row' => $rowNumber, 'message' => $message];
                }

                continue;
            }

            $previousGrade = $enrollment->grade;

            if ($previousGrade && ! $previousGrade->is_pending_default) {
                $result['overwritten']++;
            }

            $result['rows'][] = [
                'registration_course_id' => $enrollment->id,
                'row' => $rowNumber,
                'code' => $code,
                'name' => (string) $enrollment->registration->student?->name,
                'written_score' => $outcome['written_score'],
                'coursework_score' => $outcome['coursework_score'],
                'total_score' => $outcome['total_score'],
                'exam_status' => $outcome['exam_status']?->value,
                'grade_id' => $outcome['grade']->id,
                'grade_name' => $outcome['grade']->name,
                'note' => $note === null ? null : (string) $note,
                'previous_grade' => $previousGrade && ! $previousGrade->is_pending_default ? $previousGrade->name : null,
            ];
        }

        $result['missing'] = $enrollments
            ->filter(fn (RegistrationCourse $enrollment) => $enrollment->registration->status === RegistrationStatus::APPROVED)
            ->reject(fn (RegistrationCourse $enrollment, string $code) => isset($seenCodes[$code]))
            ->map(fn (RegistrationCourse $enrollment, string $code) => ['code' => $code, 'name' => (string) $enrollment->registration->student?->name])
            ->values()
            ->all();

        return $result;
    }

    /**
     * Writes analyzed rows in a single transaction.
     *
     * @param  array<int, array{registration_course_id: int, written_score: float|null, coursework_score: float|null, total_score: float|null, exam_status: string|null, grade_id: int, note: string|null}>  $rows
     */
    public function save(array $rows, User $uploadedBy): int
    {
        $uploadedAt = now();

        DB::transaction(function () use ($rows, $uploadedBy, $uploadedAt) {
            foreach ($rows as $row) {
                RegistrationCourse::query()->whereKey($row['registration_course_id'])->update([
                    'grade_id' => $row['grade_id'],
                    'written_score' => $row['written_score'],
                    'coursework_score' => $row['coursework_score'],
                    'total_score' => $row['total_score'],
                    'exam_status' => $row['exam_status'],
                    'result_note' => $row['note'],
                    'result_uploaded_at' => $uploadedAt,
                    'result_uploaded_by' => $uploadedBy->id,
                ]);
            }
        });

        return count($rows);
    }

    /**
     * Writes the course sheet shared by the registration log export and the upload page:
     * the upload columns first, then informational columns the upload ignores. Results
     * that came through an earlier upload are pre-filled, so the sheet can be re-uploaded
     * as-is and only the blank rows need filling.
     */
    public function writeCourseSheet(string $path, Course $course, Year $year, Semester $semester): void
    {
        $seats = $this->seatNumbers($year, $semester);

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            ...array_map(fn (array $spellings) => $spellings[0], array_values(self::HEADERS)),
            ...self::INFO_HEADERS,
        ]));

        $this->enrollments($course, $year, $semester, ['registration.student.level', 'registration.student.section.department'])
            ->sortKeys()
            ->each(function (RegistrationCourse $enrollment, string $code) use ($writer, $seats) {
                $student = $enrollment->registration->student;
                $studentSeats = $seats->get($student->id, []);
                $hasUploadedResult = $enrollment->result_uploaded_at !== null;

                $writer->addRow(Row::fromValues([
                    $code,
                    count($studentSeats) === 1 ? $studentSeats[0] : null,
                    ...($hasUploadedResult ? $this->uploadedMarks($enrollment) : [null, null, null, null, null]),
                    $student->name,
                    $student->level?->name,
                    $student->section?->department?->name,
                    $enrollment->registration->status?->label(),
                ]));
            });

        $writer->close();
    }

    /**
     * An uploaded result written back the way the sheet expects it.
     *
     * @return array{0: float|string|null, 1: float|null, 2: float|string|null, 3: string|null, 4: string|null}
     */
    private function uploadedMarks(RegistrationCourse $enrollment): array
    {
        $status = $enrollment->exam_status;
        $written = $enrollment->written_score !== null ? (float) $enrollment->written_score : null;

        if ($written === null && $status && $status !== ExamResultStatus::CANCELLED) {
            $written = $status->label();
        }

        return [
            $written,
            $enrollment->coursework_score !== null ? (float) $enrollment->coursework_score : null,
            $enrollment->total_score !== null ? (float) $enrollment->total_score : $status?->label(),
            $enrollment->grade?->is_pending_default ? null : $enrollment->grade?->name,
            $enrollment->result_note,
        ];
    }

    /**
     * Course enrollments of the term that may receive a result, keyed by upper-cased student code.
     *
     * @param  array<int, string>  $with
     * @return Collection<string, RegistrationCourse>
     */
    private function enrollments(Course $course, Year $year, Semester $semester, array $with = []): Collection
    {
        return RegistrationCourse::query()
            ->where('course_id', $course->id)
            ->whereHas('registration', fn (Builder $registration) => $registration
                ->where('year_id', $year->id)
                ->where('semester', $semester->value)
                ->whereIn('status', [RegistrationStatus::PENDING->value, RegistrationStatus::APPROVED->value]))
            ->with(['registration.student', 'grade', ...$with])
            ->get()
            ->filter(fn (RegistrationCourse $enrollment) => $enrollment->registration->student !== null)
            ->keyBy(fn (RegistrationCourse $enrollment) => mb_strtoupper(trim($enrollment->registration->student->username)));
    }

    /**
     * Exam seat numbers per student for the term (a student normally sits in one committee).
     *
     * @return Collection<int, array<int, int>>
     */
    private function seatNumbers(Year $year, Semester $semester): Collection
    {
        return ExamCommitteeStudent::query()
            ->whereHas('committee', fn (Builder $committee) => $committee
                ->where('year_id', $year->id)
                ->where('semester', $semester->value))
            ->get(['student_id', 'seat_number'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('seat_number')->map(fn ($seat) => (int) $seat)->all());
    }

    /**
     * Finds the position of every upload column in the header row.
     *
     * @param  array<int, mixed>  $headerCells
     * @return array{0: array<string, int>, 1: string|null}
     */
    private function resolveColumns(array $headerCells): array
    {
        $positions = [];

        foreach ($headerCells as $position => $cell) {
            $cell = $this->normalizeCell($cell);

            if ($cell !== null) {
                $positions[$this->normalizeHeader((string) $cell)][] = $position;
            }
        }

        $columns = [];
        $missing = [];

        foreach (self::HEADERS as $key => $spellings) {
            $found = collect($spellings)
                ->flatMap(fn (string $spelling) => $positions[$this->normalizeHeader($spelling)] ?? [])
                ->unique()
                ->values();

            if ($found->isEmpty()) {
                $missing[] = $spellings[0];
            } elseif ($found->count() > 1) {
                return [[], "العمود \"{$spellings[0]}\" مكرر في الملف."];
            } else {
                $columns[$key] = $found->first();
            }
        }

        if ($missing !== []) {
            return [[], 'الملف ينقصه الأعمدة: '.implode('، ', $missing).'. استخدم الشيت المُنزّل من سجل التسجيلات أو من زر نموذج الشيت.'];
        }

        return [$columns, null];
    }

    /**
     * @param  array<int, int>  $studentSeats
     */
    private function seatError(array $studentSeats, mixed $seat): ?string
    {
        if ($seat === null) {
            return $studentSeats === [] ? null : 'رقم الجلوس فارغ.';
        }

        $seatNumber = $this->asIdentifier($seat);

        if (! ctype_digit($seatNumber)) {
            return "رقم الجلوس \"{$seatNumber}\" غير صحيح.";
        }

        if ($studentSeats === []) {
            return 'لا يوجد رقم جلوس مسجل للطالب في لجان هذا الترم.';
        }

        if (! in_array((int) $seatNumber, $studentSeats, true)) {
            return "رقم الجلوس {$seatNumber} غير مطابق لكود الطالب.";
        }

        return null;
    }

    /**
     * Applies the marks rules and resolves the final grade. Problems are appended to $errors.
     *
     * @param  Collection<string, Grade>  $gradesByName
     * @param  Collection<int, Grade>  $scale  scored grades, highest range first
     * @param  array<int, string>  $errors
     * @return array{written_score: float|null, coursework_score: float|null, total_score: float|null, exam_status: ExamResultStatus|null, grade: Grade|null}
     */
    private function evaluateMarks(mixed $written, mixed $coursework, mixed $total, mixed $gradeName, Collection $gradesByName, Collection $scale, array &$errors): array
    {
        $outcome = ['written_score' => null, 'coursework_score' => null, 'total_score' => null, 'exam_status' => null, 'grade' => null];
        $errorCountBefore = count($errors);

        $sheetGrade = null;

        if ($gradeName !== null) {
            $sheetGrade = $gradesByName->get(mb_strtoupper((string) $gradeName));

            if (! $sheetGrade) {
                $errors[] = "التقدير \"{$gradeName}\" غير موجود في جدول التقديرات.";

                return $outcome;
            }

            if ($sheetGrade->is_pending_default) {
                $errors[] = "لا يمكن رفع التقدير \"{$sheetGrade->name}\" كنتيجة.";

                return $outcome;
            }
        }

        if ($written === null && $coursework === null && $total === null) {
            if (! $sheetGrade) {
                $errors[] = 'لا توجد درجات ولا تقدير. اكتب الدرجات، أو اكتب تقديراً بدون درجات مثل IC أو W أو FX.';
            } elseif ($sheetGrade->isScored()) {
                $errors[] = "التقدير {$sheetGrade->name} يحتاج إلى درجات. بدون درجات يُقبل فقط: ".$this->unscoredGradeNames($gradesByName).'.';
            }

            $outcome['grade'] = $sheetGrade;

            return $outcome;
        }

        foreach (['التحريري' => $written, 'أعمال السنة' => $coursework, 'المجموع' => $total] as $label => $value) {
            if ($value === null) {
                $errors[] = "درجة {$label} فارغة، يجب كتابة التحريري وأعمال السنة والمجموع معاً.";
            }
        }

        if (count($errors) > $errorCountBefore) {
            return $outcome;
        }

        $writtenMark = $this->parseMark($written, 'التحريري', self::WRITTEN_MAX, allowCancelled: false, errors: $errors);
        $courseworkMark = $this->parseMark($coursework, 'أعمال السنة', self::COURSEWORK_MAX, allowCancelled: false, errors: $errors);
        $totalMark = $this->parseMark($total, 'المجموع', self::TOTAL_MAX, allowCancelled: true, errors: $errors);

        if (count($errors) > $errorCountBefore) {
            return $outcome;
        }

        $failingGrade = $scale->last();

        if (! $failingGrade) {
            $errors[] = 'جدول التقديرات لا يحتوي على نطاقات درجات، راجع إعدادات التقديرات.';

            return $outcome;
        }

        if ($totalMark instanceof ExamResultStatus) {
            if ($totalMark !== ExamResultStatus::CANCELLED && $writtenMark !== $totalMark) {
                $errors[] = "المجموع \"{$totalMark->label()}\" يتطلب أن يكون التحريري \"{$totalMark->label()}\" أيضاً.";

                return $outcome;
            }

            if ($sheetGrade && $sheetGrade->id !== $failingGrade->id) {
                $errors[] = "المجموع \"{$totalMark->label()}\" يعني رسوب، يجب أن يكون التقدير {$failingGrade->name} وليس {$sheetGrade->name}.";

                return $outcome;
            }

            return [
                'written_score' => is_float($writtenMark) ? $writtenMark : null,
                'coursework_score' => is_float($courseworkMark) ? $courseworkMark : null,
                'total_score' => null,
                'exam_status' => $totalMark,
                'grade' => $failingGrade,
            ];
        }

        if (! is_float($writtenMark) || ! is_float($courseworkMark)) {
            $errors[] = 'المجموع رقم بينما التحريري أو أعمال السنة ليس رقماً. اكتب في المجموع "غائب" أو "تأديب" بنفس قيمة التحريري.';

            return $outcome;
        }

        if (abs($totalMark - ($writtenMark + $courseworkMark)) > 0.001) {
            $errors[] = 'المجموع '.$this->formatMark($totalMark).' لا يساوي التحريري + أعمال السنة ('.$this->formatMark($writtenMark + $courseworkMark).').';

            return $outcome;
        }

        $expectedGrade = $scale->first(fn (Grade $grade) => $totalMark >= (float) $grade->min_score);

        if (! $expectedGrade) {
            $errors[] = 'لا يوجد تقدير في جدول التقديرات يغطي المجموع '.$this->formatMark($totalMark).'.';

            return $outcome;
        }

        if ($sheetGrade && $sheetGrade->id !== $expectedGrade->id) {
            $errors[] = "التقدير {$sheetGrade->name} خطأ، المجموع ".$this->formatMark($totalMark)." تقديره {$expectedGrade->name}.";

            return $outcome;
        }

        return [
            'written_score' => $writtenMark,
            'coursework_score' => $courseworkMark,
            'total_score' => $totalMark,
            'exam_status' => null,
            'grade' => $expectedGrade,
        ];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function parseMark(mixed $value, string $label, int $max, bool $allowCancelled, array &$errors): float|ExamResultStatus|null
    {
        if (is_int($value) || is_float($value) || is_numeric($value)) {
            $mark = round((float) $value, 2);

            if ($mark < 0 || $mark > $max) {
                $errors[] = "درجة {$label} ({$this->formatMark($mark)}) يجب أن تكون بين 0 و {$max}.";

                return null;
            }

            return $mark;
        }

        $status = ExamResultStatus::fromSheetValue((string) $value);

        if (! $status || ($status === ExamResultStatus::CANCELLED && ! $allowCancelled)) {
            $allowed = $allowCancelled ? 'رقماً أو غائب أو تأديب أو الغاء' : 'رقماً أو غائب أو تأديب';
            $errors[] = "قيمة {$label} \"{$value}\" غير صحيحة، يجب أن تكون {$allowed}.";

            return null;
        }

        return $status;
    }

    /**
     * Trims text, converts Arabic-Indic digits to Latin ones, and turns blanks into null.
     */
    private function normalizeCell(mixed $cell): mixed
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_string($cell)) {
            $cell = trim(strtr($cell, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']));
            $cell = preg_replace('/^\x{FEFF}|[\x{200E}\x{200F}]/u', '', $cell);

            return $cell === '' ? null : $cell;
        }

        return $cell === '' ? null : $cell;
    }

    /**
     * Student codes and seat numbers may arrive as numbers (1001.0) from Excel; render them as plain text.
     */
    private function asIdentifier(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    private function normalizeHeader(string $header): string
    {
        $header = str_replace(['أ', 'إ', 'آ'], 'ا', $header);
        $header = str_replace(['ى', 'ة'], ['ي', 'ه'], $header);

        return preg_replace('/\s+/u', ' ', trim($header));
    }

    /**
     * @param  Collection<string, Grade>  $gradesByName
     */
    private function unscoredGradeNames(Collection $gradesByName): string
    {
        return $gradesByName
            ->reject(fn (Grade $grade) => $grade->isScored() || $grade->is_pending_default)
            ->pluck('name')
            ->implode('، ');
    }

    private function formatMark(float $mark): string
    {
        return rtrim(rtrim(number_format($mark, 2, '.', ''), '0'), '.');
    }
}
