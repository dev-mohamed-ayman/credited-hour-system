<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreYearRequest;
use App\Http\Requests\Admin\UpdateYearRequest;
use App\Models\Year;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class YearController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->can('years.view'), 403);

        $years = Year::latest()->get();

        return view('admin.pages.year.index', compact('years'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('years.create'), 403);

        return view('admin.pages.year.create');
    }

    public function store(StoreYearRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('years.create'), 403);

        Year::create($request->validated());

        return redirect()->route('years.index')->with('success', 'تم إضافة السنة الدراسية بنجاح');
    }

    public function edit(Year $year): View
    {
        abort_unless(auth()->user()->can('years.edit'), 403);

        return view('admin.pages.year.edit', compact('year'));
    }

    public function update(UpdateYearRequest $request, Year $year): RedirectResponse
    {
        abort_unless(auth()->user()->can('years.edit'), 403);

        $year->update($request->validated());

        return redirect()->route('years.index')->with('success', 'تم تحديث السنة الدراسية بنجاح');
    }

    public function destroy(Year $year): RedirectResponse
    {
        abort_unless(auth()->user()->can('years.delete'), 403);

        if ($year->hasBlockingRelations()) {
            return back()->with('error', $year->getBlockingRelationsMessage());
        }

        $year->delete();

        return back()->with('success', 'تم حذف السنة الدراسية بنجاح');
    }
}
