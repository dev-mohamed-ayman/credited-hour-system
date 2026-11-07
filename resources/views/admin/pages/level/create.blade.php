@extends('admin.layouts.app')
@section('title', 'إضافة فرقة دراسية')
@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">إضافة فرقة دراسية جديدة</h5>
        </div>
        <form action="{{ route('levels.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="form-group mb-4">
                    <label for="name" class="form-label">اسم الفرقة الدراسية</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="مثال: الفرقة الأولى">
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-4">
                    <label for="promotion_hours" class="form-label">ساعات الترقية</label>
                    <input type="number" min="1" name="promotion_hours" id="promotion_hours" value="{{ old('promotion_hours') }}"
                           class="form-control @error('promotion_hours') is-invalid @enderror" placeholder="مثال: 27">
                    @error('promotion_hours')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-1">الساعات المكتسبة اللي الطالب محتاجها علشان يترقى للفرقة اللي بعدها بعد الترم الصيفي. الفرقة الأخيرة بتستخدم ساعات التخرج من الإعدادات.</small>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input @error('military_required_for_males') is-invalid @enderror"
                                   type="checkbox" name="military_required_for_males" id="military_required_for_males"
                                   {{ old('military_required_for_males') ? 'checked' : '' }}>
                            <label class="form-check-label" for="military_required_for_males">
                                التربية العسكرية مطلوبة للذكور في هذه الفرقة
                            </label>
                            @error('military_required_for_males')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input @error('military_required_for_females') is-invalid @enderror"
                                   type="checkbox" name="military_required_for_females" id="military_required_for_females"
                                   {{ old('military_required_for_females') ? 'checked' : '' }}>
                            <label class="form-check-label" for="military_required_for_females">
                                التربية العسكرية مطلوبة للإناث في هذه الفرقة
                            </label>
                            @error('military_required_for_females')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label d-block">اختيار الشعب المرتبطة</label>
                    <div class="row g-3">
                        @foreach($sections as $section)
                            <div class="col-md-4">
                                <div class="form-check custom-option custom-option-basic">
                                    <label class="form-check-label custom-option-content" for="section_{{ $section->id }}">
                                        <input class="form-check-input" type="checkbox" name="section_ids[]"
                                               value="{{ $section->id }}" id="section_{{ $section->id }}"
                                               {{ is_array(old('section_ids')) && in_array($section->id, old('section_ids')) ? 'checked' : '' }}>
                                        <span class="custom-option-header">
                                            <span class="h6 mb-0">{{ $section->name }}</span>
                                        </span>
                                        <span class="custom-option-body">
                                            <small class="text-muted">{{ $section->department->name }}</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('section_ids')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a href="{{ route('levels.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
