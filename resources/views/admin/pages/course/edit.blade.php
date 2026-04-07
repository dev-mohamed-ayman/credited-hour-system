@extends('admin.layouts.app')
@section('title', 'تعديل مادة دراسية')
@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between py-3">
            <h5 class="mb-0 fw-bold">تعديل المادة: {{ $course->name }} ({{ $course->code }})</h5>
            <span class="badge bg-label-info">تحديث البيانات</span>
        </div>
        
        @livewire('admin.course.course-form', ['course' => $course])
    </div>
@endsection
