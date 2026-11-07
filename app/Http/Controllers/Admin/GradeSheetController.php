<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Semester;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Level;
use App\Models\Setting;
use App\Models\Year;
use App\Services\GradeSheetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The control's grade sheets (chs: printStudentsGrade / printSummerGrade).
 */
class GradeSheetController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.results.grade_sheets_index', [
            'years' => Year::query()->orderByDesc('year')->get(),
            'semesters' => Semester::cases(),
            'departments' => Department::query()->orderBy('name')->get(),
            'levels' => Level::query()->orderBy('id')->get(),
            'currentYearId' => Year::current()?->id,
        ]);
    }

    public function print(Request $request, GradeSheetService $sheets): View
    {
        $data = $request->validate([
            'year_id' => ['required', 'exists:years,id'],
            'semester' => ['nullable', Rule::enum(Semester::class)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'level_id' => ['nullable', 'exists:levels,id'],
            'student_code' => ['nullable', 'string', 'max:50'],
        ], [
            'year_id.required' => 'اختر السنة الدراسية.',
        ]);

        $year = Year::findOrFail($data['year_id']);
        $semester = filled($data['semester'] ?? null) ? Semester::from($data['semester']) : null;

        return view('admin.pages.results.grade_sheets_print', [
            'year' => $year,
            'semester' => $semester,
            'pages' => $sheets->pages(
                $year,
                $semester,
                isset($data['department_id']) ? (int) $data['department_id'] : null,
                isset($data['level_id']) ? (int) $data['level_id'] : null,
                $data['student_code'] ?? null,
            ),
            'settings' => Setting::query()->first(),
            'signatureRoles' => Setting::SIGNATURE_ROLES,
        ]);
    }
}
