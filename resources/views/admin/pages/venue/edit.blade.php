@extends('admin.layouts.app')
@section('title', 'تعديل مكان')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">تعديل المكان: {{ $venue->name }}</h5>
        </div>
        <form action="{{ route('venues.update', $venue->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('admin.pages.venue.fields', ['venue' => $venue])
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a href="{{ route('venues.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
