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
        abort_unless(auth()->user()->can('courses.view'), 403);

        $courses = Course::with(['department', 'level', 'sections'])->latest()->get();

        return view('admin.pages.course.index', compact('courses'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('courses.create'), 403);

        return view('admin.pages.course.create');
    }

    public function edit(Course $course): View
    {
        abort_unless(auth()->user()->can('courses.edit'), 403);

        $course->load(['prerequisites', 'sections', 'level']);

        return view('admin.pages.course.edit', compact('course'));
    }

    public function destroy(Course $course): RedirectResponse
    {
        abort_unless(auth()->user()->can('courses.delete'), 403);

        if ($course->hasBlockingRelations()) {
            return back()->with('error', $course->getBlockingRelationsMessage());
        }

        $course->delete();

        return back()->with('success', 'تم حذف المادة بنجاح');
    }
}
