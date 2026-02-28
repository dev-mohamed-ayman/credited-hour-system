<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Department;
use App\Models\Section;

class SectionController extends Controller
{
    public function index()
    {
        $sections = Section::with('department')->latest()->get();

        return view('admin.pages.section.index', compact('sections'));
    }

    public function create()
    {
        $departments = Department::all();

        return view('admin.pages.section.create', compact('departments'));
    }

    public function store(StoreSectionRequest $request)
    {
        Section::create($request->validated());

        return redirect()->route('sections.index')->with('success', 'تم إضافة الشعبة بنجاح');
    }

    public function edit(Section $section)
    {
        $departments = Department::all();

        return view('admin.pages.section.edit', compact('section', 'departments'));
    }

    public function update(UpdateSectionRequest $request, Section $section)
    {
        $section->update($request->validated());

        return redirect()->route('sections.index')->with('success', 'تم تحديث الشعبة بنجاح');
    }

    public function destroy(Section $section)
    {
        $section->delete();

        return back()->with('success', 'تم حذف الشعبة بنجاح');
    }
}
