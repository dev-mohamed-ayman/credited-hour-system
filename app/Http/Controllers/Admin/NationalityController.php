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
        $nationalities = Nationality::latest()->get();

        return view('admin.pages.nationality.index', compact('nationalities'));
    }

    public function create()
    {
        return view('admin.pages.nationality.create');
    }

    public function store(StoreNationalityRequest $request)
    {
        Nationality::create($request->validated());

        return redirect()->route('nationalities.index')->with('success', 'تم إضافة الجنسية بنجاح');
    }

    public function edit(Nationality $nationality)
    {
        return view('admin.pages.nationality.edit', compact('nationality'));
    }

    public function update(UpdateNationalityRequest $request, Nationality $nationality)
    {
        $nationality->update($request->validated());

        return redirect()->route('nationalities.index')->with('success', 'تم تحديث الجنسية بنجاح');
    }

    public function destroy(Nationality $nationality)
    {
        $nationality->delete();

        return back()->with('success', 'تم حذف الجنسية بنجاح');
    }
}
