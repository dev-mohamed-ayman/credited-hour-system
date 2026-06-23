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
        abort_unless(auth()->user()->can('sections.view'), 403);

        $sections = Section::with('department')->latest()->get();

        return view('admin.pages.section.index', compact('sections'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('sections.create'), 403);

        $departments = Department::all();

        return view('admin.pages.section.create', compact('departments'));
    }

    public function store(StoreSectionRequest $request)
    {
        abort_unless(auth()->user()->can('sections.create'), 403);

        Section::create($request->validated());

        return redirect()->route('sections.index')->with('success', 'تم إضافة الشعبة بنجاح');
    }

    public function edit(Section $section)
    {
        abort_unless(auth()->user()->can('sections.edit'), 403);

        $departments = Department::all();

        return view('admin.pages.section.edit', compact('section', 'departments'));
    }

    public function update(UpdateSectionRequest $request, Section $section)
    {
        abort_unless(auth()->user()->can('sections.edit'), 403);

        $section->update($request->validated());

        return redirect()->route('sections.index')->with('success', 'تم تحديث الشعبة بنجاح');
    }

    public function destroy(Section $section)
    {
        abort_unless(auth()->user()->can('sections.delete'), 403);

        if ($section->hasBlockingRelations()) {
            return back()->with('error', $section->getBlockingRelationsMessage());
        }

        $section->delete();

        return back()->with('success', 'تم حذف الشعبة بنجاح');
    }
}
