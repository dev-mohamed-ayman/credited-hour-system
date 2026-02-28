@extends('admin.layouts.app')
@section('title', 'تعديل دولة')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">تعديل الدولة: {{ $country->name }}</h5>
        </div>
        <form action="{{ route('countries.update', $country->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="name" class="form-label">اسم الدولة</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $country->name) }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="أدخل اسم الدولة">
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">تحديث</button>
                <a href="{{ route('countries.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
