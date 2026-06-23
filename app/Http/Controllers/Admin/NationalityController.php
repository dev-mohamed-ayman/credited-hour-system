<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNationalityRequest;
use App\Http\Requests\Admin\UpdateNationalityRequest;
use App\Models\Nationality;

class NationalityController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('nationalities.view'), 403);

        $nationalities = Nationality::latest()->get();

        return view('admin.pages.nationality.index', compact('nationalities'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('nationalities.create'), 403);

        return view('admin.pages.nationality.create');
    }

    public function store(StoreNationalityRequest $request)
    {
        abort_unless(auth()->user()->can('nationalities.create'), 403);

        Nationality::create($request->validated());

        return redirect()->route('nationalities.index')->with('success', 'تم إضافة الجنسية بنجاح');
    }

    public function edit(Nationality $nationality)
    {
        abort_unless(auth()->user()->can('nationalities.edit'), 403);

        return view('admin.pages.nationality.edit', compact('nationality'));
    }

    public function update(UpdateNationalityRequest $request, Nationality $nationality)
    {
        abort_unless(auth()->user()->can('nationalities.edit'), 403);

        $nationality->update($request->validated());

        return redirect()->route('nationalities.index')->with('success', 'تم تحديث الجنسية بنجاح');
    }

    public function destroy(Nationality $nationality)
    {
        abort_unless(auth()->user()->can('nationalities.delete'), 403);

        if ($nationality->hasBlockingRelations()) {
            return back()->with('error', $nationality->getBlockingRelationsMessage());
        }

        $nationality->delete();

        return back()->with('success', 'تم حذف الجنسية بنجاح');
    }
}
