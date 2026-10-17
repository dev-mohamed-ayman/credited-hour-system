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
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="points" class="form-label">النقاط (Points)</label>
                        <input type="number" step="0.01" min="0" max="4" name="points" id="points" value="{{ old('points', null) }}"
                               class="form-control @error('points') is-invalid @enderror" placeholder="مثال: 3.70">
                        @error('points')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="min_score" class="form-label">من درجة</label>
                        <input type="number" step="0.01" min="0" max="100" name="min_score" id="min_score" value="{{ old('min_score', null) }}"
                               class="form-control @error('min_score') is-invalid @enderror" placeholder="مثال: 90">
                        @error('min_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="max_score" class="form-label">إلى درجة</label>
                        <input type="number" step="0.01" min="0" max="100" name="max_score" id="max_score" value="{{ old('max_score', null) }}"
                               class="form-control @error('max_score') is-invalid @enderror" placeholder="مثال: 94">
                        @error('max_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <small class="text-muted"><i class="ti tabler-info-circle ti-xs me-1"></i> اترك نطاق الدرجات فارغاً للتقديرات التي تُعطى بدون درجات مثل IC و W و FX.</small>
                    </div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="counts_in_gpa" value="0">
                    <input class="form-check-input" type="checkbox" name="counts_in_gpa" id="counts_in_gpa" value="1"
                           {{ old('counts_in_gpa', true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium" for="counts_in_gpa">يدخل في حساب المعدل</label>
                    <small class="text-muted d-block mt-1">
                        <i class="ti tabler-info-circle ti-xs me-1"></i> مثلاً F و FX يدخلوا بصفر نقاط، أما IC و W لا يدخلوا
                    </small>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_pending_default" id="is_pending_default" value="1"
                           {{ old('is_pending_default') ? 'checked' : '' }}>
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
