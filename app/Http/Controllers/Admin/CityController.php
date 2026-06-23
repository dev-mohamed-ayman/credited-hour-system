<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Models\City;
use App\Models\Country;

class CityController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('cities.view'), 403);

        $cities = City::with('country')->latest()->get();

        return view('admin.pages.city.index', compact('cities'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('cities.create'), 403);

        $countries = Country::all();

        return view('admin.pages.city.create', compact('countries'));
    }

    public function store(StoreCityRequest $request)
    {
        abort_unless(auth()->user()->can('cities.create'), 403);

        City::create($request->validated());

        return redirect()->route('cities.index')->with('success', 'تم إضافة المدينة بنجاح');
    }

    public function edit(City $city)
    {
        abort_unless(auth()->user()->can('cities.edit'), 403);

        $countries = Country::all();

        return view('admin.pages.city.edit', compact('city', 'countries'));
    }

    public function update(UpdateCityRequest $request, City $city)
    {
        abort_unless(auth()->user()->can('cities.edit'), 403);

        $city->update($request->validated());

        return redirect()->route('cities.index')->with('success', 'تم تحديث المدينة بنجاح');
    }

    public function destroy(City $city)
    {
        abort_unless(auth()->user()->can('cities.delete'), 403);

        if ($city->hasBlockingRelations()) {
            return back()->with('error', $city->getBlockingRelationsMessage());
        }

        $city->delete();

        return back()->with('success', 'تم حذف المدينة بنجاح');
    }
}
