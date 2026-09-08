@php
    $feeTypeLabels = \App\Livewire\Admin\Finance\WalletsReport::FEE_TYPES;
@endphp

<div>
    <style>
        @media print {
            #layout-menu, #layout-navbar, .layout-footer, .no-print, .card-footer { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background: #fff !important; }
            .table { font-size: 11px; }
            a { text-decoration: none; color: inherit; }
        }
    </style>

    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">تقرير الحافظات (المدفوع والمتبقي)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">تقرير الحافظات</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 no-print">
            <button type="button" class="btn btn-label-success" wire:click="exportCsv">
                <i class="ti tabler-file-download me-1"></i> تصدير Excel (CSV)
            </button>
            <button type="button" class="btn btn-label-primary" onclick="window.print()">
                <i class="ti tabler-printer me-1"></i> طباعة التقرير
            </button>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <div class="card-header border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">تصفية التقرير</h5>
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
                    <label for="paymentStatus" class="form-label fw-bold">حالة السداد</label>
                    <select wire:model.live="paymentStatus" id="paymentStatus" class="form-select">
                        <option value="">الكل</option>
                        <option value="paid">مسدد بالكامل</option>
                        <option value="partial">مسدد جزئياً</option>
                        <option value="unpaid">غير مسدد</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="searchFeeType" class="form-label fw-bold">نوع المصروف</label>
                    <select wire:model.live="searchFeeType" id="searchFeeType" class="form-select">
                        <option value="">كل الأنواع</option>
                        @foreach($feeTypeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
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
                <div class="col-md-3">
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
                <div class="col-md-2">
                    <label for="searchSection" class="form-label fw-bold">الشعبة</label>
                    <select wire:model.live="searchSection" id="searchSection" class="form-select">
                        <option value="">الكل</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <div class="row g-2 align-items-end">
                        <div class="col-12">
                            <label class="form-label fw-bold mb-1">المبلغ المدفوع (ج.م)</label>
                        </div>
                        <div class="col-6">
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="paidFrom" class="form-control" placeholder="من  (مثال: 5000)">
                        </div>
                        <div class="col-6">
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="paidTo" class="form-control" placeholder="إلى  (مثال: 6000)">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row g-2 align-items-end">
                        <div class="col-12">
                            <label class="form-label fw-bold mb-1">المبلغ المتبقي / المستحق (ج.م)</label>
                        </div>
                        <div class="col-6">
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="dueFrom" class="form-control" placeholder="من  (مثال: 1000)">
                        </div>
                        <div class="col-6">
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="dueTo" class="form-control" placeholder="إلى  (مثال: 2000)">
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-muted small mt-3">
                <i class="ti tabler-info-circle me-1"></i>
                للحصول على مبلغ محدد بالضبط اجعل "من" و"إلى" متساويين (مثال: مدفوع من 5000 إلى 5000).
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-primary rounded-circle p-2 mb-1"><i class="ti tabler-users"></i></span>
                    <h5 class="mb-0">{{ number_format($totals->students_count) }}</h5>
                    <small class="text-muted">عدد الطلاب</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-secondary rounded-circle p-2 mb-1"><i class="ti tabler-receipt"></i></span>
                    <h5 class="mb-0">{{ number_format($totals->tickets_count) }}</h5>
                    <small class="text-muted">عدد الحافظات</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-warning rounded-circle p-2 mb-1"><i class="ti tabler-file-invoice"></i></span>
                    <h5 class="mb-0">{{ number_format((float) $totals->net_total, 2) }}</h5>
                    <small class="text-muted">صافي المستحق (ج.م)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-success rounded-circle p-2 mb-1"><i class="ti tabler-circle-check"></i></span>
                    <h5 class="mb-0">{{ number_format((float) $totals->paid_total, 2) }}</h5>
                    <small class="text-muted">إجمالي المدفوع (ج.م)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-danger rounded-circle p-2 mb-1"><i class="ti tabler-alert-circle"></i></span>
                    <h5 class="mb-0">{{ number_format((float) $totals->due_total, 2) }}</h5>
                    <small class="text-muted">إجمالي المتبقي (ج.م)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <span class="d-inline-flex bg-label-info rounded-circle p-2 mb-1"><i class="ti tabler-percent"></i></span>
                    <h5 class="mb-0">{{ number_format((float) $totals->discount_total, 2) }}</h5>
                    <small class="text-muted">إجمالي الخصومات (ج.م)</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center no-print" style="width: 40px;"></th>
                        <th wire:click="sortBy('name')" style="cursor: pointer;">
                            الطالب @if($sortField === 'name')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th>البيانات الدراسية</th>
                        <th class="text-center" wire:click="sortBy('tickets_count')" style="cursor: pointer;">
                            الحافظات @if($sortField === 'tickets_count')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-end" wire:click="sortBy('gross_total')" style="cursor: pointer;">
                            المستحق الأصلي @if($sortField === 'gross_total')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-end" wire:click="sortBy('discount_total')" style="cursor: pointer;">
                            الخصومات @if($sortField === 'discount_total')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-end" wire:click="sortBy('paid_total')" style="cursor: pointer;">
                            المدفوع @if($sortField === 'paid_total')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-end" wire:click="sortBy('due_total')" style="cursor: pointer;">
                            المتبقي @if($sortField === 'due_total')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-end">رصيد المحفظة</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($students as $student)
                        @php
                            $isExpanded = in_array($student->id, $expanded, true);
                            $statusLabel = $this->paymentStatusLabel($student);
                        @endphp
                        <tr>
                            <td class="text-center no-print">
                                <button type="button" class="btn btn-icon btn-sm btn-label-{{ $isExpanded ? 'warning' : 'primary' }}"
                                        wire:click="toggleDetails({{ $student->id }})"
                                        title="{{ $isExpanded ? 'إخفاء التفاصيل' : 'عرض تفاصيل الحافظات' }}">
                                    <i class="ti tabler-{{ $isExpanded ? 'chevron-up' : 'chevron-down' }}"></i>
                                </button>
                            </td>
                            <td>
                                <h6 class="mb-0">{{ $student->name }}</h6>
                                <small class="text-muted">{{ $student->username }} — {{ $student->national_id }}</small>
                            </td>
                            <td>
                                <div>{{ $student->section?->department?->name ?? '—' }}</div>
                                <small class="text-muted">{{ $student->level?->name ?? '—' }} / شعبة {{ $student->section?->name ?? '—' }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-label-secondary">{{ number_format((int) $student->tickets_count) }}</span>
                            </td>
                            <td class="text-end">{{ number_format((float) $student->gross_total, 2) }}</td>
                            <td class="text-end">
                                @if((float) $student->discount_total > 0)
                                    <span class="text-info">({{ number_format((float) $student->discount_total, 2) }})</span>
                                @else
                                    <span class="text-muted">0.00</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-success">{{ number_format((float) $student->paid_total, 2) }}</span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold {{ (float) $student->due_total > 0 ? 'text-danger' : 'text-muted' }}">
                                    {{ number_format((float) $student->due_total, 2) }}
                                </span>
                            </td>
                            <td class="text-end">{{ number_format((float) ($student->wallet?->balance ?? 0), 2) }}</td>
                            <td class="text-center">
                                @if($statusLabel === 'مسدد بالكامل')
                                    <span class="badge bg-label-success">مسدد بالكامل</span>
                                @elseif($statusLabel === 'غير مسدد')
                                    <span class="badge bg-label-danger">غير مسدد</span>
                                @else
                                    <span class="badge bg-label-warning">مسدد جزئياً</span>
                                @endif
                            </td>
                        </tr>
                        @if($isExpanded)
                            <tr>
                                <td colspan="10" class="bg-label-light">
                                    <div class="py-2">
                                        <h6 class="fw-bold mb-2">
                                            <i class="ti tabler-receipt text-primary me-1"></i>
                                            حافظات الطالب: {{ $student->name }}
                                        </h6>
                                        @if(($ticketsByStudent[$student->id] ?? collect())->isEmpty())
                                            <div class="text-muted small mb-2">لا توجد حافظات مطابقة للفلاتر الحالية.</div>
                                        @else
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0 bg-white">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>رقم الحافظة</th>
                                                            <th>بيان المصروف</th>
                                                            <th>النوع</th>
                                                            <th>السنة / الترم</th>
                                                            <th class="text-end">الإجمالي الأصلي</th>
                                                            <th class="text-end">الخصم</th>
                                                            <th class="text-end">الصافي</th>
                                                            <th class="text-end">المدفوع</th>
                                                            <th class="text-end">المتبقي</th>
                                                            <th class="text-center">الحالة</th>
                                                            <th>تاريخ السداد</th>
                                                            <th>الإيصال / الطريقة</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($ticketsByStudent[$student->id] as $ticket)
                                                            @php $ticketPaid = $ticket->isPaid(); @endphp
                                                            <tr>
                                                                <td>{{ $ticket->ticket_number }}</td>
                                                                <td>{{ $ticket->fee_name ?: ($feeTypeLabels[$ticket->fee_type] ?? $ticket->fee_type) }}</td>
                                                                <td>{{ $feeTypeLabels[$ticket->fee_type] ?? $ticket->fee_type }}</td>
                                                                <td>
                                                                    {{ $ticket->year?->year ?? '—' }}
                                                                    @if($ticket->semester)
                                                                        <small class="text-muted">/ {{ $ticket->semester->label() }}</small>
                                                                    @endif
                                                                </td>
                                                                <td class="text-end">{{ number_format((float) $ticket->grossAmount(), 2) }}</td>
                                                                <td class="text-end">{{ number_format((float) $ticket->discount_amount, 2) }}</td>
                                                                <td class="text-end">{{ number_format((float) $ticket->amount, 2) }}</td>
                                                                <td class="text-end text-success">{{ number_format($ticketPaid ? (float) $ticket->amount : 0, 2) }}</td>
                                                                <td class="text-end {{ $ticketPaid ? 'text-muted' : 'text-danger' }}">{{ number_format($ticketPaid ? 0 : (float) $ticket->amount, 2) }}</td>
                                                                <td class="text-center">
                                                                    @if($ticketPaid)
                                                                        <span class="badge bg-label-success">مسدد</span>
                                                                    @else
                                                                        <span class="badge bg-label-warning">غير مسدد</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $ticket->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                                                <td>
                                                                    @if($ticketPaid)
                                                                        <div class="small">{{ $ticket->ministerial_receipt_number ?: '—' }}</div>
                                                                        <div class="small text-muted">{{ $ticket->payment_method ?? '' }}</div>
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="ti tabler-wallet d-block mb-2" style="font-size: 3rem;"></i>
                                لا توجد حافظات مطابقة للفلاتر الحالية
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-3 no-print">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 small text-muted">عدد الصفوف:</label>
                    <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                {{ $students->links() }}
            </div>
        @endif
    </div>
</div>
