@extends('admin.layouts.app')
@section('title', 'كشوف الدرجات')

@section('content')
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">النتائج /</span> كشوف الدرجات
    </h4>

    <div class="card">
        <form action="{{ route('results.grade-sheets.print') }}" method="POST" target="_blank">
            @csrf
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="year_id">السنة الدراسية</label>
                        <select id="year_id" name="year_id" class="form-select @error('year_id') is-invalid @enderror" required>
                            @foreach($years as $year)
                                <option value="{{ $year->id }}" @selected($year->id === $currentYearId)>{{ $year->year }}</option>
                            @endforeach
                        </select>
                        @error('year_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="semester">الترم</label>
                        <select id="semester" name="semester" class="form-select">
                            <option value="">السنة كلها</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->value }}">{{ $semester->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="department_id">التخصص</label>
                        <select id="department_id" name="department_id" class="form-select">
                            <option value="">كل التخصصات</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="level_id">الفرقة</label>
                        <select id="level_id" name="level_id" class="form-select">
                            <option value="">كل الفرق</option>
                            @foreach($levels as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="student_code">كود طالب (اختياري)</label>
                        <input type="text" id="student_code" name="student_code" class="form-control">
                    </div>
                </div>
                <small class="text-muted d-block mt-3">
                    صفحة لكل طالب فيها كل ترم بمواده ودرجاته وتقديراته ونقاطه، ومعدل الترم والمعدل التراكمي والساعات، وملاحظات الترم والتوقيعات.
                    اختار "الترم الصيفي" لكشف الصيفي. الفرقة هي فرقة الطالب في السنة دي.
                </small>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success"><i class="ti tabler-printer me-1"></i> طباعة</button>
            </div>
        </form>
    </div>
@endsection
