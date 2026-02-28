@extends('admin.layouts.app')
@section('title', 'تعديل مدينة')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">تعديل المدينة: {{ $city->name }}</h5>
        </div>
        <form action="{{ route('cities.update', $city->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="country_id" class="form-label">الدولة</label>
                    <select name="country_id" id="country_id" class="form-select @error('country_id') is-invalid @enderror">
                        <option value="">اختر الدولة</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ old('country_id', $city->country_id) == $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('country_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    <label for="name" class="form-label">اسم المدينة</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $city->name) }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="أدخل اسم المدينة">
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">تحديث</button>
                <a href="{{ route('cities.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
