<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">جدول الامتحانات — اللجان</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">جدول الامتحانات</li>
                </ol>
            </nav>
        </div>
        @can('exam_schedules.create')
            <a class="btn btn-primary" href="{{ route('exam-schedules.create', ['year' => $year_id, 'semester' => $semester]) }}">
                <i class="ti tabler-plus me-1"></i> إضافة لجنة
            </a>
        @endcan
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
                <div class="col-md-3">
                    <label class="form-label" for="semester">الترم</label>
                    <select id="semester" wire:model.live="semester" class="form-select">
                        @foreach($semesters as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status_filter">الحالة</label>
                    <select id="status_filter" wire:model.live="status_filter" class="form-select">
                        <option value="">الكل</option>
                        <option value="draft">مسودة</option>
                        <option value="published">منشورة</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="search">بحث (اسم لجنة / كود طالب)</label>
                    <input type="text" id="search" wire:model.live.debounce.400ms="search" class="form-control" placeholder="لجنة 1 أو CS250001">
                </div>
            </div>
        </div>
    </div>

    @if(!$year_id || !strlen($semester))
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                اختر السنة الدراسية والترم لعرض لجان الامتحانات.
            </div>
        </div>
    @else
        @if($unassignedCount > 0)
            <div class="alert alert-warning">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <i class="ti tabler-alert-triangle"></i>
                    <span><b>{{ $unassignedCount }}</b> طالب لديهم تسجيل معتمد في هذا الترم ولم يُضافوا لأي لجنة.</span>
                    <button type="button" class="btn btn-sm btn-label-warning ms-auto" wire:click="toggleUnassigned">
                        {{ $showUnassigned ? 'إخفاء' : 'عرض الأكواد' }}
                    </button>
                </div>
                @if($showUnassigned)
                    <div class="d-flex flex-wrap gap-1 mt-3">
                        @foreach($unassigned as $student)
                            <span class="badge bg-label-dark" title="{{ $student->name }}" wire:key="unassigned-{{ $student->id }}">{{ $student->username }}</span>
                        @endforeach
                        @if($unassignedCount > $unassigned->count())
                            <span class="small text-muted">و{{ $unassignedCount - $unassigned->count() }} آخرين…</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <div class="card">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>اللجنة</th>
                            <th>المكان</th>
                            <th>الطلاب / السعة</th>
                            <th>عدد الامتحانات</th>
                            <th>الفترة</th>
                            <th>الحالة</th>
                            <th class="text-end">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($committees as $committee)
                            <tr wire:key="committee-{{ $committee->id }}">
                                <td class="fw-medium">
                                    <a href="{{ route('exam-schedules.manage', $committee) }}">{{ $committee->name }}</a>
                                </td>
                                <td>{{ $committee->venue?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $committee->members_count > $committee->capacity ? 'danger' : ($committee->members_count > 0 ? 'primary' : 'secondary') }}">
                                        {{ $committee->members_count }} / {{ $committee->capacity }}
                                    </span>
                                </td>
                                <td>{{ $committee->sessions_count }}</td>
                                <td>
                                    @if($committee->sessions_min_exam_date)
                                        {{ \Illuminate\Support\Carbon::parse($committee->sessions_min_exam_date)->format('Y-m-d') }}
                                        @if($committee->sessions_max_exam_date !== $committee->sessions_min_exam_date)
                                            → {{ \Illuminate\Support\Carbon::parse($committee->sessions_max_exam_date)->format('Y-m-d') }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="badge {{ $committee->status->badgeClass() }}">{{ $committee->status->label() }}</span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-label-primary" href="{{ route('exam-schedules.manage', $committee) }}">الطلاب والمواعيد</a>
                                    @can('exam_schedules.edit')
                                        <a class="btn btn-sm btn-label-info" href="{{ route('exam-schedules.edit', $committee) }}">تعديل</a>
                                    @endcan
                                    <a class="btn btn-sm btn-label-secondary" target="_blank" href="{{ route('exam-schedules.print.committee', $committee) }}">
                                        <i class="ti tabler-printer"></i>
                                    </a>
                                    @can('exam_schedules.publish')
                                        @if($committee->isPublished())
                                            <button type="button" class="btn btn-sm btn-label-warning"
                                                    onclick="confirmAction('إخفاء الجدول', 'سيُخفى جدول اللجنة عن الطلاب ويعود لمسودة. هل أنت متأكد؟', () => @this.call('unpublish', {{ $committee->id }}))">
                                                إخفاء
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-success"
                                                    onclick="confirmAction('نشر الجدول', 'سيظهر جدول اللجنة لطلابها فور النشر. هل أنت متأكد؟', () => @this.call('publish', {{ $committee->id }}))">
                                                نشر
                                            </button>
                                        @endif
                                    @endcan
                                    @can('exam_schedules.delete')
                                        <button type="button" class="btn btn-sm btn-label-danger"
                                                onclick="confirmAction('حذف اللجنة', 'سيتم حذف اللجنة بطلابها ومواعيدها. هل أنت متأكد؟', () => @this.call('deleteCommittee', {{ $committee->id }}))">
                                            حذف
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    لا توجد لجان في هذا الترم بعد.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-center">
                {{ $committees->links() }}
            </div>
        </div>
    @endif
</div>
