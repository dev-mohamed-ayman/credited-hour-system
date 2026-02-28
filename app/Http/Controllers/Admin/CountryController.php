<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCountryRequest;
use App\Http\Requests\Admin\UpdateCountryRequest;
use App\Models\Country;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Country::withCount('cities')->latest()->get();

        return view('admin.pages.country.index', compact('countries'));
    }

    public function create()
    {
        return view('admin.pages.country.create');
    }

    public function store(StoreCountryRequest $request)
    {
        Country::create($request->validated());

        return redirect()->route('countries.index')->with('success', 'تم إضافة الدولة بنجاح');
    }

    public function edit(Country $country)
    {
        return view('admin.pages.country.edit', compact('country'));
    }

    public function update(UpdateCountryRequest $request, Country $country)
    {
        $country->update($request->validated());

        return redirect()->route('countries.index')->with('success', 'تم تحديث الدولة بنجاح');
    }

    public function destroy(Country $country)
    {
        $country->delete();

        return back()->with('success', 'تم حذف الدولة بنجاح');
    }
}
