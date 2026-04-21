@extends('admin.layouts.app')
@section('title', 'الشهادات')
@section('content')
    <div class="card">
        <form action="{{ route('certificate-types.store') }}" method="POST">
            @csrf
            <div class="card-body row">
                <div class="form-group col-md-6 mb-4">
                    <label for="name" class="form-label">اسم الشهاده</label>
                    <select name="name" id="name" class="form-select @error('name') is-invalid @enderror">
                        <option value="">اختر الشهادة</option>
                        @foreach(\App\Models\CertificateType::NAMES as $name)
                            <option value="{{ $name }}" {{ old('name') == $name ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group col-md-6 mb-4">
                    <label for="total_score" class="form-label">المجموع الكلي</label>
                    <input type="text" name="total_score" id="total_score" value="{{ old('total_score') }}"
                           class="form-control @error('total_score') is-invalid @enderror">
                    @error('total_score')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mb-4">
                    <label class="form-label d-block mb-3">متطلبات الأقسام المتاحة</label>
                    <div class="row g-3">
                        @foreach($requirements as $requirement)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check custom-option custom-option-basic h-100">
                                    <label class="form-check-label custom-option-content"
                                        for="req{{ $requirement->id }}">
                                        <input class="form-check-input" type="checkbox" name="requirement_ids[]"
                                            value="{{ $requirement->id }}" id="req{{ $requirement->id }}"
                                            {{ in_array($requirement->id, old('requirement_ids', [])) ? 'checked' : '' }} />
                                        <span class="custom-option-header mb-1">
                                            <span class="h6 mb-0">{{ $requirement->subject_name }}</span>
                                            <span class="badge bg-label-primary">{{ $requirement->min_score }}</span>
                                        </span>
                                        <span class="custom-option-body">
                                            <small class="text-muted">القسم: {{ $requirement->department->name }}</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('requirement_ids')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mb-4">
                    <hr>
                    <label class="form-label d-block mb-3">الشعب المتاحة لهذه الشهادة</label>
                    <div class="row g-3">
                        @foreach($sections as $section)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check custom-option custom-option-basic h-100">
                                    <label class="form-check-label custom-option-content"
                                        for="sec{{ $section->id }}">
                                        <input class="form-check-input" type="checkbox" name="section_ids[]"
                                            value="{{ $section->id }}" id="sec{{ $section->id }}"
                                            {{ in_array($section->id, old('section_ids', [])) ? 'checked' : '' }} />
                                        <span class="custom-option-header mb-1">
                                            <span class="h6 mb-0">{{ $section->name }}</span>
                                        </span>
                                        <span class="custom-option-body">
                                            <small class="text-muted">القسم: {{ $section->department->name }}</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('section_ids')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ</button>
            </div>
        </form>
    </div>
@endsection
