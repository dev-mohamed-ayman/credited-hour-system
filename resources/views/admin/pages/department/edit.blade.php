@extends('admin.layouts.app')
@section('title', 'تعديل التخصص')
@section('content')
    <div class="card">
        <form action="{{ route('departments.update', $department->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body row">
                <div class="form-group col-md-6 mb-4">
                    <label for="name" class="form-label">اسم التخصص</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $department->name) }}"
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group col-md-6 mb-4">
                    <label for="code" class="form-label">الكود</label>
                    <input type="text" name="code" id="code" value="{{ old('code', $department->code) }}"
                        class="form-control @error('code') is-invalid @enderror">
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <hr class="my-4">

                <h5 class="mb-4">متطلبات التخصص</h5>

                <div class="form-repeater">
                    <div data-repeater-list="requirements">
                        @if($department->requirements->count() > 0)
                            @foreach($department->requirements as $requirement)
                                <div data-repeater-item>
                                    <div class="row">
                                        <div class="mb-4 col-lg-6 col-xl-5 col-12 mb-lg-0">
                                            <label class="form-label" for="subject_name">اسم المادة</label>
                                            <input type="text" name="subject_name" class="form-control"
                                                value="{{ $requirement->subject_name }}"
                                                placeholder="مثلاً: اللغة الإنجليزية" />
                                        </div>
                                        <div class="mb-4 col-lg-6 col-xl-5 col-12 mb-lg-0">
                                            <label class="form-label" for="min_score">الدرجة الصغرى</label>
                                            <input type="number" step="0.01" name="min_score" class="form-control"
                                                value="{{ $requirement->min_score }}"
                                                placeholder="مثلاً: 50" />
                                        </div>
                                        <div class="col-lg-12 col-xl-2 col-12 d-flex align-items-end mb-0">
                                            <button class="btn btn-label-danger mt-0 waves-effect" data-repeater-delete
                                                type="button">
                                                <i class="fa-solid fa-trash me-1"></i>
                                                <span class="align-middle">حذف</span>
                                            </button>
                                        </div>
                                    </div>
                                    <hr class="mt-4">
                                </div>
                            @endforeach
                        @else
                            <div data-repeater-item>
                                <div class="row">
                                    <div class="mb-4 col-lg-6 col-xl-5 col-12 mb-lg-0">
                                        <label class="form-label" for="subject_name">اسم المادة</label>
                                        <input type="text" name="subject_name" class="form-control"
                                            placeholder="مثلاً: اللغة الإنجليزية" />
                                    </div>
                                    <div class="mb-4 col-lg-6 col-xl-5 col-12 mb-lg-0">
                                        <label class="form-label" for="min_score">الدرجة الصغرى</label>
                                        <input type="number" step="0.01" name="min_score" class="form-control"
                                            placeholder="مثلاً: 50" />
                                    </div>
                                    <div class="col-lg-12 col-xl-2 col-12 d-flex align-items-end mb-0">
                                        <button class="btn btn-label-danger mt-0 waves-effect" data-repeater-delete
                                            type="button">
                                            <i class="fa-solid fa-trash me-1"></i>
                                            <span class="align-middle">حذف</span>
                                        </button>
                                    </div>
                                </div>
                                <hr class="mt-4">
                            </div>
                        @endif
                    </div>
                    <div class="mb-0">
                        <button class="btn btn-primary waves-effect" data-repeater-create type="button">
                            <i class="fa-solid fa-plus me-1"></i>
                            <span class="align-middle">إضافة متطلب</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">تحديث</button>
                <a href="{{ route('departments.index') }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection

@push('vendor-scripts')
    <script src="{{ asset('assets/vendor/libs/jquery-repeater/jquery-repeater.js') }}"></script>
@endpush

@push('page-scripts')
    <script>
        $(function () {
            var formRepeater = $('.form-repeater');

            if (formRepeater.length) {
                var row = 2;
                var col = 1;
                formRepeater.repeater({
                    show: function () {
                        var fromControl = $(this).find('.form-control, .form-select');
                        var formLabel = $(this).find('.form-label');

                        fromControl.each(function (i) {
                            var id = 'form-repeater-' + row + '-' + col;
                            $(fromControl[i]).attr('id', id);
                            $(formLabel[i]).attr('for', id);
                            col++;
                        });

                        row++;

                        $(this).slideDown();
                    },
                    hide: function (deleteElement) {
                        if (confirm('هل أنت متأكد من حذف هذا المتطلب؟')) {
                            $(this).slideUp(deleteElement);
                        }
                    }
                });
            }
        });
    </script>
@endpush
