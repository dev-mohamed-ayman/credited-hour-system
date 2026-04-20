@extends('admin.layouts.app')
@section('title', 'طباعة شهادات التخرج')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endpush

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">خيارات طباعة شهادات التخرج</h3>
                </div>

                <form action="{{ route('print.certificates.print') }}" method="POST" target="_blank">
                    @csrf
                    <div class="card-body">

                        <div class="alert alert-info">
                            يمكنك اختيار طلاب محددين لطباعة شهاداتهم، <strong>أو</strong> استخدام الفلاتر بالأسفل لطباعة مجموعة كاملة.
                        </div>

                        <!-- Specific Students -->
                        <div class="form-group border-bottom pb-4 mb-4">
                            <label>اختيار طلاب محددين (اختياري)</label>
                            <select class="select2bs4" multiple="multiple" name="student_ids[]" data-placeholder="اختر كود/اسم الطالب" style="width: 100%;">
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}">{{ $student->username }} - {{ $student->name }} - (جلوس: {{ $student->seat_number ?? 'لا يوجد' }})</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Filters -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>التخصص</label>
                                    <select class="form-control" name="department_id">
                                        <option value="">كل التخصصات</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>الشعبة</label>
                                    <select class="form-control" name="section_id">
                                        <option value="">كل الشعب</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>الفرقة</label>
                                    <select class="form-control" name="level_id">
                                        <option value="">كل الفرق</option>
                                        @foreach($levels as $level)
                                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 mt-3">
                                <div class="form-group">
                                    <label>الفصل الدراسي</label>
                                    <select class="form-control" name="semester_graduated">
                                        <option value="">كل الفصول</option>
                                        <option value="الأول">الفصل الأول</option>
                                        <option value="الثاني">الفصل الثاني</option>
                                        <option value="الصيفي">الفصل الصيفي</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 mt-3">
                                <div class="form-group">
                                    <label>عام التخرج</label>
                                    <input type="text" class="form-control" name="year_graduated" placeholder="مثال: 2024/2025">
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-print"></i> طباعة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('vendor-scripts')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endpush

@push('page-scripts')
    <script>
        $(function () {
            $('.select2bs4').select2({
                dir: "rtl",
                placeholder: "اختر كود/اسم الطالب"
            });
        });
    </script>
@endpush
