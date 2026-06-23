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
        abort_unless(auth()->user()->can('countries.view'), 403);

        $countries = Country::withCount('cities')->latest()->get();

        return view('admin.pages.country.index', compact('countries'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('countries.create'), 403);

        return view('admin.pages.country.create');
    }

    public function store(StoreCountryRequest $request)
    {
        abort_unless(auth()->user()->can('countries.create'), 403);

        Country::create($request->validated());

        return redirect()->route('countries.index')->with('success', 'تم إضافة الدولة بنجاح');
    }

    public function edit(Country $country)
    {
        abort_unless(auth()->user()->can('countries.edit'), 403);

        return view('admin.pages.country.edit', compact('country'));
    }

    public function update(UpdateCountryRequest $request, Country $country)
    {
        abort_unless(auth()->user()->can('countries.edit'), 403);

        $country->update($request->validated());

        return redirect()->route('countries.index')->with('success', 'تم تحديث الدولة بنجاح');
    }

    public function destroy(Country $country)
    {
        abort_unless(auth()->user()->can('countries.delete'), 403);

        if ($country->hasBlockingRelations()) {
            return back()->with('error', $country->getBlockingRelationsMessage());
        }

        $country->delete();

        return back()->with('success', 'تم حذف الدولة بنجاح');
    }
}
