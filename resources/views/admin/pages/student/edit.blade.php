@extends('admin.layouts.app')
@section('title', 'تعديل بيانات طالب')
@section('content')
    <div class="card">
        @livewire('admin.student.edit', ['student' => $student])
    </div>
@endsection
@push('vendor-styles')
    <link rel="stylesheet" href="{{asset('assets/vendor/libs/dropzone/dropzone.css')}}"/>
@endpush
@push('vendor-scripts')
    <script src="{{asset('assets/vendor/libs/dropzone/dropzone.js')}}"></script>
@endpush
@push('page-scripts')
    <script src="{{asset('assets/js/forms-file-upload.js')}}"></script>
@endpush
