<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function printCardsIndex()
    {
        $students = Student::orderBy('name')->get(['id', 'username', 'name']);
        $departments = Department::all();
        $sections = Section::all();
        $levels = Level::all();

        return view('admin.pages.student.print_card_index', compact('students', 'departments', 'sections', 'levels'));
    }

    public function printCards(Request $request)
    {
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
        $students = Student::orderBy('name')->get(['id', 'username', 'name']);
        $departments = Department::all();
        $sections = Section::all();
        $levels = Level::all();

        return view('admin.pages.student.print_seat_number_index', compact('students', 'departments', 'sections', 'levels'));
    }

    public function printSeatNumbers(Request $request)
    {
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

        return view('admin.pages.student.print_seat_number', compact('students', 'settings'));
    }

    public function printCertificatesIndex(): \Illuminate\View\View
    {
        $students = Student::orderBy('name')->get(['id', 'username', 'name', 'seat_number']);
        $departments = Department::all();
        $sections = Section::all();
        $levels = Level::all();

        return view('admin.pages.student.print_certificate_index', compact('students', 'departments', 'sections', 'levels'));
    }

    public function printCertificates(Request $request): \Illuminate\View\View
    {
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
            if ($request->filled('year_graduated')) {
                $query->where('year_graduated', $request->year_graduated);
            }
            if ($request->filled('semester_graduated')) {
                $query->where('semester_graduated', $request->semester_graduated);
            }
        }

        $students = $query->get();
        $settings = Setting::query()->firstOrCreate();

        return view('admin.pages.student.print_certificate', compact('students', 'settings'));
    }

    public function index()
    {
        return view('admin.pages.student.index');
    }

    public function create()
    {
        return view('admin.pages.student.create');
    }

    public function edit(Student $student)
    {
        return view('admin.pages.student.edit', compact('student'));
    }

    public function show(Student $student)
    {
        return view('admin.pages.student.show', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        return $request;
    }
}
