<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicAdvisor;

class AcademicAdvisorController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('academic_advisors.view'), 403);

        return view('admin.academic-advisors.index');
    }

    public function create()
    {
        abort_unless(auth()->user()->can('academic_advisors.create'), 403);

        return view('admin.academic-advisors.create');
    }

    public function edit(AcademicAdvisor $academicAdvisor)
    {
        abort_unless(auth()->user()->can('academic_advisors.edit'), 403);

        return view('admin.academic-advisors.edit', compact('academicAdvisor'));
    }
}
