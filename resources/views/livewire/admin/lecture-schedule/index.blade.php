<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">جدول المحاضرات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">جدول المحاضرات</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">التخصص</label>
                    <select wire:model.live="department_id" class="form-select">
                        <option value="">كل التخصصات</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الفرقة</label>
                    <select wire:model.live="level_id" class="form-select">
                        <option value="">كل الفرق</option>
                        @foreach($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الترم</label>
                    <select wire:model.live="semester" class="form-select">
                        <option value="">كل الترمات</option>
                        @foreach($semesters as $term)
                            <option value="{{ $term }}">{{ $term }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المادة</label>
                    <select wire:model.live="course_id" class="form-select">
                        <option value="">اختر المادة</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->name }} ({{ $course->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if($selectedCourse)
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">جلسات: {{ $selectedCourse->name }} — {{ $selectedCourse->department->name }} — {{ $selectedCourse->level->name }} — الترم {{ $selectedCourse->semester }}</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('lecture-schedules.grid', $selectedCourse) }}" class="btn btn-label-info btn-sm">
                        <i class="fa-solid fa-table-cells me-1"></i> الجدول الأسبوعي
                    </a>
                    @can('lecture_schedules.create')
                        <a href="{{ route('lecture-schedules.create', $selectedCourse) }}" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-plus me-1"></i> إضافة جلسة
                        </a>
                    @endcan
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th>اليوم</th>
                            <th>الوقت</th>
                            <th>المكان</th>
                            <th>السكاشن</th>
                            <th>الإجمالي / السعة</th>
                            <th>الحالة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($sessions as $session)
                            @php $flag = $flags[$session->id] ?? ['over_capacity' => false, 'missing_sections' => false, 'total' => 0]; @endphp
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $session->day->label() }}</td>
                                <td>{{ $session->start_time }} — {{ $session->end_time }}</td>
                                <td>{{ $session->venue->name }} <span class="badge bg-label-secondary">{{ $session->venue->type->label() }}</span></td>
                                <td>{{ $session->sectionNumbersLabel() }}</td>
                                <td>
                                    {{ $flag['total'] ?? 0 }} / {{ $session->venue->capacity ?? '—' }}
                                </td>
                                <td>
                                    @if($flag['over_capacity'])
                                        <span class="badge bg-danger">تجاوز السعة</span>
                                    @endif
                                    @if($flag['missing_sections'])
                                        <span class="badge bg-warning text-dark">لم تحدد السكاشن</span>
                                    @endif
                                    @if(!$flag['over_capacity'] && !$flag['missing_sections'])
                                        <span class="badge bg-label-success">سليمة</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @can('lecture_schedules.edit')
                                        <a href="{{ route('lecture-schedules.edit', $session) }}" class="btn btn-sm btn-success">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    @endcan
                                    @can('lecture_schedules.delete')
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmAction('حذف الجلسة', 'هل أنت متأكد من حذف جلسة المحاضرة هذه؟', () => @this.call('deleteSession', {{ $session->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">لا توجد جلسات محاضرات لهذه المادة بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                اختر المادة من الفلاتر أعلاه لعرض جلسات المحاضرة الخاصة بها.
            </div>
        </div>
    @endif
</div>
