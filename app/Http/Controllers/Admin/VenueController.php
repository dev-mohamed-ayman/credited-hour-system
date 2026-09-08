<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVenueRequest;
use App\Http\Requests\Admin\UpdateVenueRequest;
use App\Models\Venue;

class VenueController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('venues.view'), 403);

        $venues = Venue::withCount('lectureSchedules')->latest()->get();

        return view('admin.pages.venue.index', compact('venues'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('venues.create'), 403);

        return view('admin.pages.venue.create');
    }

    public function store(StoreVenueRequest $request)
    {
        abort_unless(auth()->user()->can('venues.create'), 403);

        Venue::create($request->validated());

        return redirect()->route('venues.index')->with('success', 'تم إضافة المكان بنجاح');
    }

    public function edit(Venue $venue)
    {
        abort_unless(auth()->user()->can('venues.edit'), 403);

        return view('admin.pages.venue.edit', compact('venue'));
    }

    public function update(UpdateVenueRequest $request, Venue $venue)
    {
        abort_unless(auth()->user()->can('venues.edit'), 403);

        $venue->update($request->validated());

        return redirect()->route('venues.index')->with('success', 'تم تحديث المكان بنجاح');
    }

    public function destroy(Venue $venue)
    {
        abort_unless(auth()->user()->can('venues.delete'), 403);

        if ($venue->hasBlockingRelations()) {
            return back()->with('error', $venue->getBlockingRelationsMessage());
        }

        $venue->delete();

        return back()->with('success', 'تم حذف المكان بنجاح');
    }
}
