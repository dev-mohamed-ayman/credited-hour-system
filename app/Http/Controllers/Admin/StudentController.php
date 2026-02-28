<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index()
    {
        return view('admin.pages.student.index');
    }

    public function create()
    {
        return view('admin.pages.student.create');
    }

    public function edit(Student $student)
    {
        return view('admin.pages.student.edit', compact('student'));
    }

    public function show(Student $student)
    {
        return view('admin.pages.student.show', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        return $request;
    }
}
