@extends('admin.layouts.app')
@section('title', 'تعديل سنة دراسية')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">تعديل السنة الدراسية: {{ $year->year }}</h5>
        </div>
        <form action="{{ route('years.update', $year->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group mb-4">
                    <label for="year" class="form-label">السنة الدراسية</label>
                    <input type="text" name="year" id="year" value="{{ old('year', $year->year) }}"
                           class="form-control @error('year') is-invalid @enderror" placeholder="مثال: 2026/2027">
                    <small class="text-muted">يجب أن يكون التنسيق مثل 2026/2027</small>
                    @error('year')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">تحديث</button>
                <a href="{{ route('years.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
