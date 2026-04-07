<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        $courses = Course::with(['department', 'sections'])->latest()->get();
        return view('admin.pages.course.index', compact('courses'));
    }

    public function create(): View
    {
        return view('admin.pages.course.create');
    }

    public function edit(Course $course): View
    {
        return view('admin.pages.course.edit', compact('course'));
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();
        return back()->with('success', 'تم حذف المادة بنجاح');
    }
}
