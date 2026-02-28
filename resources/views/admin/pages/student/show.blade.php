@extends('admin.layouts.app')
@section('title', 'تفاصيل الطالب: ' . $student->name)
@section('content')
    <livewire:admin.student.show :student="$student" />
@endsection
