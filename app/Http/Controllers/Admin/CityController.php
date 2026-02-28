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
        $cities = City::with('country')->latest()->get();

        return view('admin.pages.city.index', compact('cities'));
    }

    public function create()
    {
        $countries = Country::all();

        return view('admin.pages.city.create', compact('countries'));
    }

    public function store(StoreCityRequest $request)
    {
        City::create($request->validated());

        return redirect()->route('cities.index')->with('success', 'تم إضافة المدينة بنجاح');
    }

    public function edit(City $city)
    {
        $countries = Country::all();

        return view('admin.pages.city.edit', compact('city', 'countries'));
    }

    public function update(UpdateCityRequest $request, City $city)
    {
        $city->update($request->validated());

        return redirect()->route('cities.index')->with('success', 'تم تحديث المدينة بنجاح');
    }

    public function destroy(City $city)
    {
        $city->delete();

        return back()->with('success', 'تم حذف المدينة بنجاح');
    }
}
