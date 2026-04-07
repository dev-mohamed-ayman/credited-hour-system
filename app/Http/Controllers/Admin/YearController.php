<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Year;
use App\Http\Requests\Admin\StoreYearRequest;
use App\Http\Requests\Admin\UpdateYearRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class YearController extends Controller
{
    public function index(): View
    {
        $years = Year::latest()->get();
        return view('admin.pages.year.index', compact('years'));
    }

    public function create(): View
    {
        return view('admin.pages.year.create');
    }

    public function store(StoreYearRequest $request): RedirectResponse
    {
        Year::create($request->validated());
        return redirect()->route('years.index')->with('success', 'تم إضافة السنة الدراسية بنجاح');
    }

    public function edit(Year $year): View
    {
        return view('admin.pages.year.edit', compact('year'));
    }

    public function update(UpdateYearRequest $request, Year $year): RedirectResponse
    {
        $year->update($request->validated());
        return redirect()->route('years.index')->with('success', 'تم تحديث السنة الدراسية بنجاح');
    }

    public function destroy(Year $year): RedirectResponse
    {
        $year->delete();
        return back()->with('success', 'تم حذف السنة الدراسية بنجاح');
    }
}
