<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AcademicAdvisor;

class AcademicAdvisorController extends Controller
{
    public function index()
    {
        return view('admin.academic-advisors.index');
    }

    public function create()
    {
        return view('admin.academic-advisors.create');
    }

    public function edit(AcademicAdvisor $academicAdvisor)
    {
        return view('admin.academic-advisors.edit', compact('academicAdvisor'));
    }
}
