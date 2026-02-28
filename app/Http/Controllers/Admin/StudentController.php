<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function store(Request $request)
    {
        return $request;
    }
}
