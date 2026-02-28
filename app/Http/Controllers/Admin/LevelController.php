<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLevelRequest;
use App\Http\Requests\Admin\UpdateLevelRequest;
use App\Models\Level;
use App\Models\Section;

class LevelController extends Controller
{
    public function index()
    {
        $levels = Level::with('sections')->latest()->get();

        return view('admin.pages.level.index', compact('levels'));
    }

    public function create()
    {
        $sections = Section::with('department')->get();

        return view('admin.pages.level.create', compact('sections'));
    }

    public function store(StoreLevelRequest $request)
    {
        $level = Level::create($request->validated());
        $level->sections()->sync($request->input('section_ids', []));

        return redirect()->route('levels.index')->with('success', 'تم إضافة الفرقة الدراسية بنجاح');
    }

    public function edit(Level $level)
    {
        $sections = Section::with('department')->get();

        return view('admin.pages.level.edit', compact('level', 'sections'));
    }

    public function update(UpdateLevelRequest $request, Level $level)
    {
        $level->update($request->validated());
        $level->sections()->sync($request->input('section_ids', []));

        return redirect()->route('levels.index')->with('success', 'تم تحديث الفرقة الدراسية بنجاح');
    }

    public function destroy(Level $level)
    {
        $level->delete();

        return back()->with('success', 'تم حذف الفرقة الدراسية بنجاح');
    }
}
