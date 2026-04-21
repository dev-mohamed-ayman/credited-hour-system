@extends('admin.layouts.app')
@section('title', 'إضافة شعبة')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">إضافة شعبة جديدة</h5>
        </div>
        <form action="{{ route('sections.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="department_id" class="form-label">التخصص</label>
                    <select name="department_id" id="department_id" class="form-select @error('department_id') is-invalid @enderror">
                        <option value="">اختر التخصص</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('department_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    <label for="name" class="form-label">اسم الشعبة</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="أدخل اسم الشعبة">
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    <label for="cgpa" class="form-label">CGPA</label>
                    <input type="number" step="0.01" name="cgpa" id="cgpa" value="{{ old('cgpa') }}"
                           class="form-control @error('cgpa') is-invalid @enderror" placeholder="أدخل CGPA">
                    @error('cgpa')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a href="{{ route('sections.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
