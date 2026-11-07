<?php

namespace App\Services;

use App\Enums\Semester;
use App\Models\Student;
use App\Models\StudentTermNote;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

/**
 * The control's per-term notes uploaded from a sheet of two columns, كود الطالب and
 * الملاحظة (chs: course_code = note). Like the result sheet, the whole sheet is checked
 * first and nothing is written while any row is wrong. An empty note clears it.
 */
class TermNoteUploadService
{
    /**
     * @var array<string, array<int, string>>
     */
    public const HEADERS = [
        'code' => ['كود الطالب'],
        'note' => ['الملاحظة', 'الملاحظه', 'ملاحظة', 'ملاحظه'],
    ];

    /**
     * @param  array<int, array<int, mixed>>  $sheetRows  raw rows, header first
     * @return array{errors: array<int, array{row: int, message: string}>, rows: array<int, array{student_id: int, code: string, name: string, note: string|null}>}
     */
    public function analyze(array $sheetRows): array
    {
        $result = ['errors' => [], 'rows' => []];

        $header = array_map(fn ($cell) => $this->normalizeHeader((string) $cell), $sheetRows[0] ?? []);
        $columns = [];

        foreach (self::HEADERS as $key => $spellings) {
            $position = collect($spellings)->map(fn (string $spelling) => array_search($this->normalizeHeader($spelling), $header, true))->first(fn ($found) => $found !== false);

            if ($position === null) {
                $result['errors'][] = ['row' => 1, 'message' => 'الملف ينقصه العمود "'.$spellings[0].'".'];

                return $result;
            }

            $columns[$key] = $position;
        }

        $rows = array_slice($sheetRows, 1, preserve_keys: true);
        $codes = collect($rows)->map(fn (array $cells) => $this->text($cells[$columns['code']] ?? null))->filter()->map(fn (string $code) => mb_strtoupper($code));
        $students = Student::query()
            ->whereIn(DB::raw('UPPER(username)'), $codes->unique()->values()->all() ?: [''])
            ->get(['id', 'username', 'name'])
            ->keyBy(fn (Student $student) => mb_strtoupper($student->username));

        $seen = [];

        foreach ($rows as $index => $cells) {
            $rowNumber = $index + 1;
            $code = $this->text($cells[$columns['code']] ?? null);
            $note = $this->text($cells[$columns['note']] ?? null);

            if ($code === null && $note === null) {
                continue;
            }

            if ($code === null) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => 'كود الطالب فارغ.'];

                continue;
            }

            $code = mb_strtoupper($code);

            if (isset($seen[$code])) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => "كود الطالب {$code} مكرر (ورد من قبل في السطر {$seen[$code]})."];

                continue;
            }

            $seen[$code] = $rowNumber;
            $student = $students->get($code);

            if ($student === null) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => "لا يوجد طالب بالكود {$code}."];

                continue;
            }

            if ($note !== null && mb_strlen($note) > 255) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => 'الملاحظة أطول من 255 حرفاً.'];

                continue;
            }

            $result['rows'][] = ['student_id' => $student->id, 'code' => $code, 'name' => $student->name, 'note' => $note];
        }

        return $result;
    }

    /**
     * @param  array<int, array{student_id: int, note: string|null}>  $rows
     */
    public function save(array $rows, Year $year, Semester $semester, User $user): int
    {
        DB::transaction(function () use ($rows, $year, $semester, $user) {
            foreach ($rows as $row) {
                $key = ['student_id' => $row['student_id'], 'year_id' => $year->id, 'semester' => $semester->value];

                if ($row['note'] === null) {
                    StudentTermNote::query()->where($key)->delete();

                    continue;
                }

                StudentTermNote::updateOrCreate($key, ['note' => $row['note'], 'uploaded_by' => $user->id]);
            }
        });

        return count($rows);
    }

    private function text(mixed $cell): ?string
    {
        if (is_float($cell) && floor($cell) === $cell) {
            $cell = (string) (int) $cell;
        }

        $cell = trim((string) $cell);

        return $cell === '' ? null : $cell;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\x{FEFF}/u', '', trim($header));
        $header = str_replace(['أ', 'إ', 'آ'], 'ا', $header);

        return preg_replace('/\s+/u', ' ', str_replace(['ى', 'ة'], ['ي', 'ه'], $header));
    }
}
