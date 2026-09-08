<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">جدول الامتحانات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">جدول الامتحانات</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="year_id">السنة الدراسية</label>
                    <select id="year_id" wire:model.live="year_id" class="form-select">
                        <option value="">اختر السنة</option>
                        @foreach($years as $y)
                            <option value="{{ $y->id }}">{{ $y->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="semester">الترم</label>
                    <select id="semester" wire:model.live="semester" class="form-select">
                        @foreach($semesters as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="department_id">التخصص</label>
                    <select id="department_id" wire:model.live="department_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="level_id">الفرقة</label>
                    <select id="level_id" wire:model.live="level_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach($levels as $l)
                            <option value="{{ $l->id }}">{{ $l->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status_filter">حالة الجلسة</label>
                    <select id="status_filter" wire:model.live="status_filter" class="form-select">
                        <option value="">الكل</option>
                        <option value="not_set">لم تُحدد</option>
                        <option value="draft">مسودة</option>
                        <option value="published">منشورة</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sort">ترتيب حسب</label>
                    <select id="sort" wire:model.live="sort" class="form-select">
                        <option value="name">اسم المادة</option>
                        <option value="examinees">عدد الممتحنين</option>
                        <option value="exam_date">تاريخ الامتحان</option>
                        <option value="status">الحالة</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if(!$year_id || !strlen($semester))
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                اختر السنة الدراسية والترم لعرض المواد التي لها تسجيلات معتمدة.
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>المادة</th>
                            <th>التخصص</th>
                            <th>الفرقة</th>
                            <th>الممتحنون</th>
                            <th>التاريخ</th>
                            <th>الوقت</th>
                            <th>النوع</th>
                            <th>الحالة</th>
                            <th class="text-end">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courses as $course)
                            @php($session = $sessions->get($course->id))
                            <tr wire:key="course-{{ $course->id }}">
                                <td class="fw-medium">{{ $course->name }}</td>
                                <td>{{ $course->department->name }}</td>
                                <td>{{ $course->level->name }}</td>
                                <td>
                                    <span class="badge bg-label-{{ ($counts[$course->id] ?? 0) > 0 ? 'primary' : 'danger' }}">
                                        {{ $counts[$course->id] ?? 0 }}
                                    </span>
                                </td>
                                <td>{{ $session?->exam_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>
                                    @if($session)
                                        {{ substr($session->start_time, 0, 5) }} – {{ substr($session->end_time, 0, 5) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $session?->type?->label() ?? '—' }}</td>
                                <td>
                                    @if($session)
                                        <span class="badge {{ $session->status->badgeClass() }}">{{ $session->status->label() }}</span>
                                        @if($stale->get($session->id))
                                            <span class="badge bg-label-warning">توزيع غير محدّث</span>
                                        @endif
                                    @else
                                        <span class="badge bg-label-secondary">لم تُحدد</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(!$session)
                                        @can('exam_schedules.create')
                                            <a class="btn btn-sm btn-primary"
                                               href="{{ route('exam-schedules.create', $course) }}?year={{ $year_id }}">
                                                تحديد
                                            </a>
                                        @endcan
                                    @else
                                        @can('exam_schedules.edit')
                                            <a class="btn btn-sm btn-label-info" href="{{ route('exam-schedules.edit', $session) }}">تعديل</a>
                                            <a class="btn btn-sm btn-label-primary" href="{{ route('exam-schedules.seating', $session) }}">التوزيع</a>
                                        @endcan
                                        @can('exam_schedules.publish')
                                            @if($session->status === \App\Enums\ExamSessionStatus::DRAFT)
                                                <button type="button" class="btn btn-sm btn-success"
                                                        onclick="confirmAction('نشر الجدول', 'سيظهر جدول الامتحان للطلاب فور النشر. هل أنت متأكد؟', () => @this.call('publish', {{ $session->id }}))">
                                                    نشر
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-label-warning"
                                                        onclick="confirmAction('إخفاء الجدول', 'سيُخفى جدول الامتحان عن الطلاب ويعود لمسودة. هل أنت متأكد؟', () => @this.call('unpublish', {{ $session->id }}))">
                                                    إخفاء
                                                </button>
                                            @endif
                                        @endcan
                                        @can('exam_schedules.delete')
                                            <button type="button" class="btn btn-sm btn-label-danger"
                                                    onclick="confirmAction('حذف الجلسة', 'هل أنت متأكد من حذف جلسة الامتحان هذه؟', () => @this.call('deleteSession', {{ $session->id }}))">
                                                حذف
                                            </button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    لا توجد مواد مسجّل بها في هذا الترم (تسجيلات معتمدة).
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-center">
                {{ $courses->links() }}
            </div>
        </div>
    @endif
</div>
