@extends('admin.layouts.app')
@section('title', 'إضافة مكان')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">إضافة مكان جديد</h5>
        </div>
        <form action="{{ route('venues.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('admin.pages.venue.fields', ['venue' => null])
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a href="{{ route('venues.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
