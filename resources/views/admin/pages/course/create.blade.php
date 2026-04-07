@extends('admin.layouts.app')
@section('title', 'إضافة مادة دراسية')
@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between py-3">
            <h5 class="mb-0 fw-bold">إضافة مادة دراسية جديدة</h5>
            <span class="badge bg-label-primary">بيانات المادة</span>
        </div>
        
        @livewire('admin.course.course-form')
    </div>
@endsection
