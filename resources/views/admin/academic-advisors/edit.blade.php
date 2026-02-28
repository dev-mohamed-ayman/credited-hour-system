@extends('admin.layouts.app')

@section('title', 'تعديل مرشد أكاديمي')

@section('content')
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">المرشدين الأكاديميين /</span> تعديل مرشد أكاديمي
    </h4>

    <livewire:admin.academic-advisor.form :advisor="$academicAdvisor" />
@endsection
