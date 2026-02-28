@extends('admin.layouts.app')
@section('title', 'تعديل جنسية')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">تعديل الجنسية: {{ $nationality->name }}</h5>
        </div>
        <form action="{{ route('nationalities.update', $nationality->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="name" class="form-label">الجنسية</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $nationality->name) }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="أدخل اسم الجنسية">
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">تحديث</button>
                <a href="{{ route('nationalities.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
