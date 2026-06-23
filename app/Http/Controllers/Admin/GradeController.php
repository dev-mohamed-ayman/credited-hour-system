<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGradeRequest;
use App\Http\Requests\Admin\UpdateGradeRequest;
use App\Models\Grade;

class GradeController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('grades.view'), 403);

        $grades = Grade::query()->orderBy('order')->orderBy('name')->get();

        return view('admin.pages.grade.index', compact('grades'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('grades.create'), 403);

        return view('admin.pages.grade.create');
    }

    public function store(StoreGradeRequest $request)
    {
        abort_unless(auth()->user()->can('grades.create'), 403);

        $data = $request->validated();
        $data['is_pending_default'] = $request->boolean('is_pending_default');

        Grade::create($data);

        return redirect()->route('grades.index')->with('success', 'تم إضافة التقييم بنجاح');
    }

    public function edit(Grade $grade)
    {
        abort_unless(auth()->user()->can('grades.edit'), 403);

        return view('admin.pages.grade.edit', compact('grade'));
    }

    public function update(UpdateGradeRequest $request, Grade $grade)
    {
        abort_unless(auth()->user()->can('grades.edit'), 403);

        $data = $request->validated();
        $data['is_pending_default'] = $request->boolean('is_pending_default');

        $grade->update($data);

        return redirect()->route('grades.index')->with('success', 'تم تحديث التقييم بنجاح');
    }

    public function destroy(Grade $grade)
    {
        abort_unless(auth()->user()->can('grades.delete'), 403);

        if ($grade->hasBlockingRelations()) {
            return back()->with('error', $grade->getBlockingRelationsMessage());
        }

        $grade->delete();

        return back()->with('success', 'تم حذف التقييم بنجاح');
    }
}
