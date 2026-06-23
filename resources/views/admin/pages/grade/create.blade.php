@extends('admin.layouts.app')
@section('title', 'إضافة تقييم')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">إضافة تقييم جديد</h5>
        </div>
        <form action="{{ route('grades.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="name" class="form-label">اسم التقييم</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="مثال: A+">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group mb-3">
                    <label for="order" class="form-label">الترتيب</label>
                    <input type="number" name="order" id="order" value="{{ old('order', 0) }}"
                           class="form-control @error('order') is-invalid @enderror" min="0">
                    @error('order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_pending_default" id="is_pending_default" value="1"
                           {{ old('is_pending_default') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_pending_default">تقييم افتراضي (Pending)</label>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a href="{{ route('grades.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
