@extends('admin.layouts.app')
@section('title', 'تعديل تقييم')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">تعديل التقييم: {{ $grade->name }}</h5>
        </div>
        <form action="{{ route('grades.update', $grade->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="name" class="form-label">اسم التقييم</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $grade->name) }}"
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group mb-3">
                    <label for="order" class="form-label">الترتيب</label>
                    <input type="number" name="order" id="order" value="{{ old('order', $grade->order) }}"
                           class="form-control @error('order') is-invalid @enderror" min="0">
                    @error('order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_pending_default" id="is_pending_default" value="1"
                           {{ old('is_pending_default', $grade->is_pending_default) ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium" for="is_pending_default">تقييم افتراضي (Pending)</label>
                    <small class="text-muted d-block mt-1">
                        <i class="ti tabler-info-circle ti-xs me-1"></i> تفعيل ده هيلغي التفعيل من أي تقييم تاني
                    </small>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2 border-top pt-4">
                <a href="{{ route('grades.index') }}" class="btn btn-label-secondary waves-effect">
                    <i class="ti tabler-arrow-left me-1"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary waves-effect waves-light">
                    <i class="ti tabler-device-floppy me-1"></i> حفظ
                </button>
            </div>
        </form>
    </div>
@endsection
