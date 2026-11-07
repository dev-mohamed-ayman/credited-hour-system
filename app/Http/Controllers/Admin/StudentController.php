<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Semester;
use App\Enums\TermOutcome;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ExamCommitteeStudent;
use App\Models\Level;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Year;
use App\Services\GpaCalculator;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function printCardsIndex()
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $students = Student::orderBy('name')->get(['id', 'username', 'name']);
        $departments = Department::all();
        $sections = Section::all();
        $levels = Level::all();

        return view('admin.pages.student.print_card_index', compact('students', 'departments', 'sections', 'levels'));
    }

    public function printCards(Request $request)
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $query = Student::query();

        if ($request->has('student_ids') && count($request->student_ids) > 0) {
            $query->whereIn('id', $request->student_ids);
        } else {
            if ($request->filled('department_id')) {
                $sectionIds = Section::where('department_id', $request->department_id)->pluck('id');
                $query->whereIn('section_id', $sectionIds);
            }
            if ($request->filled('section_id')) {
                $query->where('section_id', $request->section_id);
            }
            if ($request->filled('level_id')) {
                $query->where('level_id', $request->level_id);
            }
        }

        $students = $query->get();
        $settings = Setting::query()->firstOrCreate();

        // Optional: ensure print preview uses full view
        return view('admin.pages.student.print_card', compact('students', 'settings'));
    }

    public function printSeatNumbersIndex()
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $students = Student::with($this->currentExamSeats())->orderBy('name')->get(['id', 'username', 'name']);
        $departments = Department::all();
        $sections = Section::all();
        $levels = Level::all();

        return view('admin.pages.student.print_seat_number_index', compact('students', 'departments', 'sections', 'levels'));
    }

    public function printSeatNumbers(Request $request)
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $query = Student::with(['section.department', 'level', ...$this->currentExamSeats()]);

        if ($request->has('student_ids') && count($request->student_ids) > 0) {
            $query->whereIn('id', $request->student_ids);
        } else {
            if ($request->filled('department_id')) {
                $sectionIds = Section::where('department_id', $request->department_id)->pluck('id');
                $query->whereIn('section_id', $sectionIds);
            }
            if ($request->filled('section_id')) {
                $query->where('section_id', $request->section_id);
            }
            if ($request->filled('level_id')) {
                $query->where('level_id', $request->level_id);
            }
        }

        $students = $query->get();
        $settings = Setting::query()->firstOrCreate();

        return view('admin.pages.student.print_seat_number', compact('students', 'settings'));
    }

    public function printCertificatesIndex(): \Illuminate\View\View
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $students = Student::orderBy('name')->get(['id', 'username', 'name', 'seat_number']);
        $departments = Department::all();
        $sections = Section::all();
        $levels = Level::all();
        $years = Year::query()->orderByDesc('year')->get();
        $semesters = Semester::cases();

        return view('admin.pages.student.print_certificate_index', compact('students', 'departments', 'sections', 'levels', 'years', 'semesters'));
    }

    public function printCertificates(Request $request): \Illuminate\View\View
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $query = Student::with(['section.department', 'level']);

        if ($request->has('student_ids') && count($request->student_ids) > 0) {
            $query->whereIn('id', $request->student_ids);
        } else {
            if ($request->filled('department_id')) {
                $sectionIds = Section::where('department_id', $request->department_id)->pluck('id');
                $query->whereIn('section_id', $sectionIds);
            }
            if ($request->filled('section_id')) {
                $query->where('section_id', $request->section_id);
            }
            if ($request->filled('level_id')) {
                $query->where('level_id', $request->level_id);
            }
            $graduationYearId = $request->integer('graduation_year_id') ?: null;
            $graduationSemester = Semester::tryFrom((string) $request->input('graduation_semester'));

            if ($graduationYearId !== null || $graduationSemester !== null) {
                $query->whereHas('termRecords', fn ($records) => $records
                    ->where('outcome', TermOutcome::GRADUATED->value)
                    ->whereHas('closure', fn ($closure) => $closure
                        ->when($graduationYearId, fn ($byYear) => $byYear->where('year_id', $graduationYearId))
                        ->when($graduationSemester, fn ($bySemester) => $bySemester->where('semester', $graduationSemester->value))));
            }
        }

        $query->with(['termRecords' => fn ($records) => $records
            ->where('outcome', TermOutcome::GRADUATED->value)
            ->with('closure.year')
            ->latest('id')]);

        $students = $query->with(['examCommitteeMemberships.committee.year'])->get();
        $settings = Setting::query()->firstOrCreate();

        $calculator = app(GpaCalculator::class);
        $cumulatives = $students->mapWithKeys(function (Student $student) use ($calculator) {
            $cumulative = $calculator->cumulative($student);

            return [$student->id => [
                'cgpa' => $cumulative['total_hours'] > 0 ? $cumulative['gpa'] : null,
                'classification' => $cumulative['total_hours'] > 0 ? $calculator->classification($cumulative['gpa']) : '',
            ]];
        });

        $lastSeats = $students->mapWithKeys(fn (Student $student) => [
            $student->id => $student->examCommitteeMemberships
                ->sortBy(fn (ExamCommitteeStudent $seat) => $seat->committee->year?->year.'|'.$seat->committee->semester->order())
                ->last(),
        ]);

        return view('admin.pages.student.print_certificate', compact('students', 'settings', 'cumulatives', 'lastSeats'));
    }

    /**
     * Eager load for each student's seat in the current term's exam committees.
     *
     * @return array<string, \Closure>
     */
    private function currentExamSeats(): array
    {
        $year = Year::current();
        $semester = $year?->getCurrentSemester();

        return [
            'examCommitteeMemberships' => fn ($query) => $query->forTerm($year, $semester)->with('committee'),
        ];
    }

    public function printReport(Student $student): \Illuminate\View\View
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        $student->load(['level', 'section.department', 'academicAdvisor', 'warnings']);
        $settings = Setting::query()->firstOrCreate();

        return view('admin.pages.student.print_report', compact('student', 'settings'));
    }

    public function index()
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        return view('admin.pages.student.index');
    }

    public function create()
    {
        abort_unless(auth()->user()->can('students.create'), 403);

        return view('admin.pages.student.create');
    }

    public function edit(Student $student)
    {
        abort_unless(auth()->user()->can('students.edit'), 403);

        return view('admin.pages.student.edit', compact('student'));
    }

    public function show(Student $student)
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        return view('admin.pages.student.show', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        abort_unless(auth()->user()->can('students.edit'), 403);

        return $request;
    }

    public function searchIndex()
    {
        abort_unless(auth()->user()->can('students.view'), 403);

        return view('admin.pages.student.search');
    }
}
