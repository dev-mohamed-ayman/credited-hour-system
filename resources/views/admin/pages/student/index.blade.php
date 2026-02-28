@extends('admin.layouts.app')
@section('title', 'الطلاب')
@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة الطلاب</h5>
            <a class="btn btn-primary waves-effect waves-light" href="{{ route('students.create') }}">
                <i class="fa-solid fa-plus me-1"></i> إضافة طالب
            </a>
        </div>

    </div>
@endsection
