<div>
    <style>
        @media print {
            #layout-menu, #layout-navbar, .layout-footer, .no-print, .card-footer { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background: #fff !important; }
            .table { font-size: 10px; }
            a { text-decoration: none; color: inherit; }
        }
    </style>

    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">مراجعة الطلاب (المصاريف الدراسية والأخرى)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">مراجعة الطلاب</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 no-print">
            <button type="button" class="btn btn-label-success" wire:click="exportCsv">
                <i class="ti tabler-file-download me-1"></i> تصدير Excel (CSV)
            </button>
            <button type="button" class="btn btn-label-primary" onclick="window.print()">
                <i class="ti tabler-printer me-1"></i> طباعة
            </button>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <div class="card-header border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">تصفية المراجعة</h5>
            <button type="button" class="btn btn-sm btn-label-secondary" wire:click="clearFilters">
                <i class="ti tabler-x me-1"></i> مسح الفلاتر
            </button>
        </div>
        <div class="card-body pt-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="searchStudent" class="form-label fw-bold">اسم / كود / رقم قومي الطالب</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti tabler-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="searchStudent" id="searchStudent" class="form-control" placeholder="بحث...">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="studyRemaining" class="form-label fw-bold">المصاريف الدراسية</label>
                    <select wire:model.live="studyRemaining" id="studyRemaining" class="form-select">
                        <option value="">الكل</option>
                        <option value="paid">مسددة بالكامل</option>
                        <option value="unpaid">غير مسددة (عليها باقي)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="otherRemaining" class="form-label fw-bold">المصاريف الأخرى (إضافية/عسكرية/أخرى)</label>
                    <select wire:model.live="otherRemaining" id="otherRemaining" class="form-select">
                        <option value="">الكل</option>
                        <option value="paid">مسددة بالكامل</option>
                        <option value="unpaid">غير مسددة (عليها باقي)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="searchYear" class="form-label fw-bold">السنة الدراسية</label>
                    <select wire:model.live="searchYear" id="searchYear" class="form-select">
                        <option value="">الكل</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="searchSemester" class="form-label fw-bold">الترم</label>
                    <select wire:model.live="searchSemester" id="searchSemester" class="form-select">
                        <option value="">الكل</option>
                        @foreach(\App\Enums\Semester::cases() as $semester)
                            <option value="{{ $semester->value }}">{{ $semester->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="searchDepartment" class="form-label fw-bold">القسم</label>
                    <select wire:model.live="searchDepartment" id="searchDepartment" class="form-select">
                        <option value="">الكل</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="searchLevel" class="form-label fw-bold">الفرقة</label>
                    <select wire:model.live="searchLevel" id="searchLevel" class="form-select">
                        <option value="">الكل</option>
                        @foreach($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="searchSection" class="form-label fw-bold">الشعبة</label>
                    <select wire:model.live="searchSection" id="searchSection" class="form-select">
                        <option value="">الكل</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="searchStudyStatus" class="form-label fw-bold">تصنيف الطالب</label>
                    <select wire:model.live="searchStudyStatus" id="searchStudyStatus" class="form-select">
                        <option value="">الكل</option>
                        @foreach(\App\Enums\Student\StudyStatus::cases() as $studyStatus)
                            <option value="{{ $studyStatus->value }}">{{ $studyStatus->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="searchStudentStatus" class="form-label fw-bold">الحالة الدراسية</label>
                    <select wire:model.live="searchStudentStatus" id="searchStudentStatus" class="form-select">
                        <option value="">الكل</option>
                        @foreach(\App\Enums\Student\StudentStatus::cases() as $studentStatus)
                            <option value="{{ $studentStatus->value }}">{{ $studentStatus->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-primary rounded-circle p-2 mb-1"><i class="ti tabler-users"></i></span>
                    <h5 class="mb-0">{{ number_format($totals->students_count) }}</h5>
                    <small class="text-muted">عدد الطلاب</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-success rounded-circle p-2 mb-1"><i class="ti tabler-circle-check"></i></span>
                    <h5 class="mb-0">{{ number_format((float) $totals->study_paid, 2) }}</h5>
                    <small class="text-muted">المدفوع دراسياً (ج.م)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-danger rounded-circle p-2 mb-1"><i class="ti tabler-alert-circle"></i></span>
                    <h5 class="mb-0">{{ number_format((float) $totals->study_remaining, 2) }}</h5>
                    <small class="text-muted">باقي الدراسي (ج.م)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-warning rounded-circle p-2 mb-1"><i class="ti tabler-alert-triangle"></i></span>
                    <h5 class="mb-0">{{ number_format((float) $totals->other_remaining, 2) }}</h5>
                    <small class="text-muted">باقي الأخرى (ج.م)</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-center">
                        <th rowspan="2" class="align-middle" wire:click="sortBy('name')" style="cursor: pointer;">
                            الطالب @if($sortField === 'name')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th rowspan="2" class="align-middle">البيانات الدراسية</th>
                        <th rowspan="2" class="align-middle text-center" wire:click="sortBy('registered_hours')" style="cursor: pointer;">
                            الساعات @if($sortField === 'registered_hours')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th colspan="4" class="border-start">المصاريف الدراسية (ج.م)</th>
                        <th colspan="4" class="border-start">المصاريف الأخرى (ج.م)</th>
                        <th rowspan="2" class="align-middle text-end">المحفظة</th>
                        <th rowspan="2" class="align-middle text-center">الحالة</th>
                    </tr>
                    <tr class="text-center small">
                        <th class="border-start">الإجمالي</th>
                        <th>الخصم</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th class="border-start">الإجمالي</th>
                        <th>الخصم</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($students as $student)
                        @php
                            $statusLabel = $this->paymentStatusLabel($student);
                            $studyRem = $this->remainingOf($student, 'study');
                            $otherRem = $this->remainingOf($student, 'other');
                        @endphp
                        <tr>
                            <td>
                                <h6 class="mb-0">{{ $student->name }}</h6>
                                <small class="text-muted">{{ $student->username }} — {{ $student->national_id }}</small>
                            </td>
                            <td>
                                <div>{{ $student->section?->department?->name ?? '—' }} / {{ $student->level?->name ?? '—' }} / شعبة {{ $student->section?->name ?? '—' }}</div>
                                <small class="text-muted">
                                    {{ $student->study_status?->label() ?? '—' }}
                                    — {{ $student->status?->label() ?? '—' }}
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-label-secondary">{{ number_format((int) $student->registered_hours) }}</span>
                            </td>
                            <td class="text-end border-start">{{ number_format((float) $student->study_gross, 2) }}</td>
                            <td class="text-end">
                                @if((float) $student->study_discount > 0)
                                    <span class="text-info">({{ number_format((float) $student->study_discount, 2) }})</span>
                                @else
                                    <span class="text-muted">0.00</span>
                                @endif
                            </td>
                            <td class="text-end text-success fw-bold">{{ number_format((float) $student->study_paid, 2) }}</td>
                            <td class="text-end fw-bold {{ $studyRem > 0.005 ? 'text-danger' : 'text-muted' }}">{{ number_format($studyRem, 2) }}</td>
                            <td class="text-end border-start">{{ number_format((float) $student->other_gross, 2) }}</td>
                            <td class="text-end">
                                @if((float) $student->other_discount > 0)
                                    <span class="text-info">({{ number_format((float) $student->other_discount, 2) }})</span>
                                @else
                                    <span class="text-muted">0.00</span>
                                @endif
                            </td>
                            <td class="text-end text-success fw-bold">{{ number_format((float) $student->other_paid, 2) }}</td>
                            <td class="text-end fw-bold {{ $otherRem > 0.005 ? 'text-danger' : 'text-muted' }}">{{ number_format($otherRem, 2) }}</td>
                            <td class="text-end">{{ number_format((float) ($student->wallet?->balance ?? 0), 2) }}</td>
                            <td class="text-center">
                                <span class="badge {{ match($statusLabel) {
                                    'مسدد بالكامل' => 'bg-label-success',
                                    'غير مسدد' => 'bg-label-danger',
                                    'مسدد جزئياً' => 'bg-label-warning',
                                    default => 'bg-label-secondary',
                                } }}">{{ $statusLabel }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="ti tabler-users-search d-block mb-2" style="font-size: 3rem;"></i>
                                لا توجد نتائج مطابقة للفلاتر الحالية
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-3 no-print">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small text-muted">عدد الصفوف:</label>
                <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 90px;">
                    @foreach(\App\Livewire\Admin\Finance\StudentPaymentsReview::PER_PAGES as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            @if($students->hasPages())
                {{ $students->links() }}
            @else
                <small class="text-muted">إجمالي {{ number_format($totals->students_count) }} طالب</small>
            @endif
        </div>
    </div>
</div>
