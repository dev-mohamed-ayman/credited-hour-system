@extends('admin.layouts.app')
@section('title', 'إضافة جنسية')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">إضافة جنسية جديدة</h5>
        </div>
        <form action="{{ route('nationalities.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="name" class="form-label">الجنسية</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="أدخل اسم الجنسية">
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a href="{{ route('nationalities.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
