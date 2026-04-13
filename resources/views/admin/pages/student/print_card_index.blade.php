@extends('admin.layouts.app')
@section('title', 'طباعة الكارنيهات')

@push('css')
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">خيارات طباعة الكارنيهات</h3>
                </div>
                
                <form action="{{ route('print.student.cards.print') }}" method="POST" target="_blank">
                    @csrf
                    <div class="card-body">
                        
                        <div class="alert alert-info">
                            يمكنك اختيار طلاب محددين لطباعة كارنيهاتهم، <strong>أو</strong> استخدام الفلاتر بالأسفل لطباعة مجموعة كاملة.
                        </div>

                        <!-- Specific Students -->
                        <div class="form-group border-bottom pb-4 mb-4">
                            <label>اختيار طلاب محددين (اختياري)</label>
                            <select class="select2bs4" multiple="multiple" name="student_ids[]" data-placeholder="اختر كود/اسم الطالب" style="width: 100%;">
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}">{{ $student->username }} - {{ $student->name }}</option>
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

@push('js')
    <!-- Select2 -->
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(function () {
            //Initialize Select2 Elements
            $('.select2bs4').select2({
                theme: 'bootstrap4',
                dir: "rtl"
            });
        });
    </script>
@endpush
