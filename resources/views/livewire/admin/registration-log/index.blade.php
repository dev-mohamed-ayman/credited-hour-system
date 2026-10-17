<div x-on:afterprint.window="$wire.set('printAll', false)">
    <style>
        @media print {
            #layout-menu, #layout-navbar, .layout-footer, .no-print, .card-footer { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background: #fff !important; }
            .table { font-size: 11px; }
            .table-responsive { overflow: visible !important; }
        }
    </style>

    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">سجل التسجيلات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">سجل التسجيلات</li>
                </ol>
            </nav>
        </div>
        @if($enrollments)
            <div class="d-flex gap-2 no-print">
                <button type="button" class="btn btn-label-success" wire:click="exportCsv">
                    <i class="ti tabler-file-download me-1"></i> تصدير Excel (CSV)
                </button>
                <button type="button" class="btn btn-label-primary" wire:click="printList" wire:loading.attr="disabled" wire:target="printList">
                    <i class="ti tabler-printer me-1"></i> طباعة الكشف
                </button>
            </div>
        @endif
    </div>

    <div class="card mb-4 no-print">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="yearId" class="form-label fw-bold">السنة الدراسية</label>
                    <select wire:model.live="yearId" id="yearId" class="form-select">
                        <option value="">اختر السنة</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="semester" class="form-label fw-bold">الترم</label>
                    <select wire:model.live="semester" id="semester" class="form-select">
                        <option value="">اختر الترم</option>
                        @foreach($semesters as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="departmentId" class="form-label fw-bold">التخصص</label>
                    <select wire:model.live="departmentId" id="departmentId" class="form-select">
                        <option value="">كل التخصصات</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="courseId" class="form-label fw-bold">المادة</label>
                    <select wire:model.live="courseId" id="courseId" class="form-select">
                        <option value="">اختر المادة</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if(! $enrollments)
        <div class="alert alert-info no-print d-flex align-items-center">
            <i class="ti tabler-info-circle me-2"></i>
            <span>اختر السنة والترم والمادة لعرض الطلاب المسجلين.</span>
        </div>
    @else
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between border-bottom">
                <div>
                    <h5 class="card-title mb-0">{{ $selectedCourse?->code }} — {{ $selectedCourse?->name }}</h5>
                    <small class="text-muted d-none d-print-block">
                        {{ $years->firstWhere('id', $yearId)?->year }} — {{ \App\Enums\Semester::tryFrom($semester)?->label() }}
                    </small>
                </div>
                <span class="badge bg-label-primary fs-6">عدد الطلاب المسجلين: {{ $enrollments->total() }}</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الكود</th>
                            <th>الطالب</th>
                            <th>الفرقة</th>
                            <th>التخصص</th>
                            <th class="text-center">حالة التسجيل</th>
                            <th class="text-center">التقدير</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($enrollments as $enrollment)
                            @php($student = $enrollment->registration->student)
                            <tr wire:key="enrollment-{{ $enrollment->id }}">
                                <td>{{ $enrollments->firstItem() + $loop->index }}</td>
                                <td>{{ $student?->username }}</td>
                                <td>{{ $student?->name }}</td>
                                <td>{{ $student?->level?->name ?? '—' }}</td>
                                <td>{{ $student?->section?->department?->name ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $enrollment->registration->status === \App\Enums\RegistrationStatus::APPROVED ? 'bg-label-success' : 'bg-label-warning' }}">
                                        {{ $enrollment->registration->status?->label() }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $enrollment->grade?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">لا يوجد طلاب مسجلين في هذه المادة في الترم المحدد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($enrollments->hasPages())
                <div class="card-footer">{{ $enrollments->links() }}</div>
            @endif
        </div>
    @endif
</div>
