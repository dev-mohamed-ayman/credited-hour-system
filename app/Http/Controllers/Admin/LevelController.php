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
        abort_unless(auth()->user()->can('levels.view'), 403);

        $levels = Level::with('sections')->latest()->get();

        return view('admin.pages.level.index', compact('levels'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('levels.create'), 403);

        $sections = Section::with('department')->get();

        return view('admin.pages.level.create', compact('sections'));
    }

    public function store(StoreLevelRequest $request)
    {
        abort_unless(auth()->user()->can('levels.create'), 403);

        $level = Level::create($request->validated());
        $level->sections()->sync($request->input('section_ids', []));

        return redirect()->route('levels.index')->with('success', 'تم إضافة الفرقة الدراسية بنجاح');
    }

    public function edit(Level $level)
    {
        abort_unless(auth()->user()->can('levels.edit'), 403);

        $sections = Section::with('department')->get();

        return view('admin.pages.level.edit', compact('level', 'sections'));
    }

    public function update(UpdateLevelRequest $request, Level $level)
    {
        abort_unless(auth()->user()->can('levels.edit'), 403);

        $level->update($request->validated());
        $level->sections()->sync($request->input('section_ids', []));

        return redirect()->route('levels.index')->with('success', 'تم تحديث الفرقة الدراسية بنجاح');
    }

    public function destroy(Level $level)
    {
        abort_unless(auth()->user()->can('levels.delete'), 403);

        if ($level->hasBlockingRelations()) {
            return back()->with('error', $level->getBlockingRelationsMessage());
        }

        $level->delete();

        return back()->with('success', 'تم حذف الفرقة الدراسية بنجاح');
    }
}
