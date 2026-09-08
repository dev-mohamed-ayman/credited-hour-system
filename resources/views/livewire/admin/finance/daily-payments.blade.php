@php
    $feeTypeLabels = \App\Livewire\Admin\Finance\WalletsReport::FEE_TYPES;
@endphp

<div>
    <style>
        @media print {
            #layout-menu, #layout-navbar, .layout-footer, .no-print { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background: #fff !important; }
            .table { font-size: 10px; }
        }
    </style>

    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">مراجعة اليوميات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">مراجعة اليوميات</li>
                </ol>
            </nav>
        </div>
        @if($window)
            <div class="d-flex gap-2 no-print">
                <button type="button" class="btn btn-label-success" wire:click="exportCsv">
                    <i class="ti tabler-file-download me-1"></i> تصدير المدفوعات (CSV)
                </button>
                <button type="button" class="btn btn-label-primary" onclick="window.print()">
                    <i class="ti tabler-printer me-1"></i> طباعة المراجعة
                </button>
            </div>
        @endif
    </div>

    {{-- Current Day Status Card --}}
    <div class="card mb-4 no-print">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">حالة الخزينة</h5>
            @if($currentOpenDay)
                <span class="badge bg-success">يوم مفتوح</span>
            @else
                <span class="badge bg-danger">لا يوجد يوم مفتوح</span>
            @endif
        </div>
        <div class="card-body">
            @if($currentOpenDay)
                <div class="row align-items-center g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">تاريخ اليوم المالي</div>
                        <div class="fw-bold">{{ $currentOpenDay->date }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">وقت الفتح</div>
                        <div class="fw-bold">{{ $currentOpenDay->start_date?->format('Y-m-d H:i') }}</div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button wire:click="closeDay" class="btn btn-danger">
                            <i class="ti tabler-lock me-1"></i> غلق اليوم
                        </button>
                    </div>
                </div>
            @else
                <form wire:submit.prevent="openDay">
                    <div class="row align-items-end g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="openDayDate">تاريخ اليوم المالي</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-calendar"></i></span>
                                <input type="date" wire:model="selectedDate" id="openDayDate" class="form-control">
                            </div>
                            @error('selectedDate') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti tabler-lock-open me-1"></i> فتح اليوم
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- Review Mode Card --}}
    <div class="card mb-4 no-print">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">تصفية المراجعة</h5>
        </div>
        <div class="card-body pt-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">نوع المراجعة</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="reviewMode" id="modeDay" value="day" wire:model.live="reviewMode" autocomplete="off">
                        <label class="btn btn-label-primary" for="modeDay">يوم مالي</label>
                        <input type="radio" class="btn-check" name="reviewMode" id="modeRange" value="period" wire:model.live="reviewMode" autocomplete="off">
                        <label class="btn btn-label-primary" for="modeRange">فترة محددة</label>
                    </div>
                </div>

                @if($reviewMode === 'day')
                    <div class="col-md-5">
                        <label for="reviewDate" class="form-label fw-bold">اليوم المالي</label>
                        <select wire:model.live="selectedDate" id="reviewDate" class="form-select">
                            @forelse($days as $day)
                                <option value="{{ $day->date }}">{{ $day->date }} ({{ $day->end_date ? 'مغلق' : 'مفتوح' }})</option>
                            @empty
                                <option value="">لا توجد يوميات بعد</option>
                            @endforelse
                        </select>
                    </div>
                @else
                    <div class="col-md-4">
                        <label for="rangeFrom" class="form-label fw-bold">من (تاريخ ووقت)</label>
                        <input type="datetime-local" wire:model.live="rangeFrom" id="rangeFrom" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="rangeTo" class="form-label fw-bold">إلى (تاريخ ووقت)</label>
                        <div class="d-flex gap-2">
                            <input type="datetime-local" wire:model.live="rangeTo" id="rangeTo" class="form-control">
                            <button type="button" class="btn btn-label-secondary" wire:click="clearRange">
                                <i class="ti tabler-x"></i>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            @if($reviewMode === 'period' && !$window)
                <div class="alert alert-label-icon alert-warning d-flex align-items-center mt-3 mb-0" role="alert">
                    <i class="ti tabler-info-circle alert-icon-container fs-5 text-warning me-2 ms-3"></i>
                    <h6 class="alert-heading mb-0">أدخل تاريخي بداية ونهاية صحيحين (النهاية ليست قبل البداية) لعرض المراجعة.</h6>
                </div>
            @elseif($reviewMode === 'day' && !$window)
                <div class="alert alert-label-icon alert-warning d-flex align-items-center mt-3 mb-0" role="alert">
                    <i class="ti tabler-info-circle alert-icon-container fs-5 text-warning me-2 ms-3"></i>
                    <h6 class="alert-heading mb-0">لا يوجد يوم مالي بهذا التاريخ — اختر يوماً من القائمة أو أنشئ واحداً من بطاقة حالة الخزينة.</h6>
                </div>
            @endif
        </div>
    </div>

    @if($window && $breakdown)
        {{-- Headline Totals --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-2">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <span class="d-inline-flex bg-label-primary rounded-circle p-2 mb-1"><i class="ti tabler-receipt"></i></span>
                        <h5 class="mb-0">{{ number_format($breakdown['count']) }}</h5>
                        <small class="text-muted">عملية سداد</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <span class="d-inline-flex bg-label-success rounded-circle p-2 mb-1"><i class="ti tabler-cash"></i></span>
                        <h5 class="mb-0">{{ number_format($breakdown['net_total'], 2) }}</h5>
                        <small class="text-muted">المحصَّل (ج.م)</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <span class="d-inline-flex bg-label-secondary rounded-circle p-2 mb-1"><i class="ti tabler-file-invoice"></i></span>
                        <h5 class="mb-0">{{ number_format($breakdown['gross_total'], 2) }}</h5>
                        <small class="text-muted">قبل الخصم (ج.م)</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <span class="d-inline-flex bg-label-info rounded-circle p-2 mb-1"><i class="ti tabler-percent"></i></span>
                        <h5 class="mb-0">{{ number_format($breakdown['discount_total'], 2) }}</h5>
                        <small class="text-muted">خصومات (ج.م)</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <span class="d-inline-flex bg-label-warning rounded-circle p-2 mb-1"><i class="ti tabler-gift"></i></span>
                        <h5 class="mb-0">{{ number_format($breakdown['full_discount_count']) }}</h5>
                        <small class="text-muted">سداد بخصم كامل</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="card h-100">
                    <div class="card-body text-center py-3">
                        <span class="d-inline-flex bg-label-danger rounded-circle p-2 mb-1"><i class="ti tabler-hash"></i></span>
                        <h5 class="mb-0">{{ number_format($breakdown['ministerial_count']) }}</h5>
                        <small class="text-muted">إيصالات وزارية</small>
                        @if($breakdown['ministerial_first'])
                            <div class="text-muted" style="font-size: .7rem;">{{ $breakdown['ministerial_first'] }} ← {{ $breakdown['ministerial_last'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment Methods & Fee Types --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header border-bottom">
                        <h6 class="mb-0"><i class="ti tabler-credit-card me-1"></i> التفصيل حسب طريقة الدفع</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>الطريقة</th><th class="text-center">عدد</th><th class="text-end">المبلغ (ج.م)</th></tr>
                            </thead>
                            <tbody>
                                @foreach(\App\Livewire\Admin\Finance\DailyPayments::PAYMENT_METHODS as $method => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-center">{{ number_format($breakdown['by_method'][$method]['count']) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($breakdown['by_method'][$method]['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="table-light">
                                    <td class="fw-bold">الإجمالي</td>
                                    <td class="text-center fw-bold">{{ number_format($breakdown['count']) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($breakdown['net_total'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ti tabler-categories-2 me-1"></i> التفصيل حسب نوع المصروف</h6>
                        @if($cashiersOnDuty->isNotEmpty())
                            <small class="text-muted">المحصّلون: {{ $cashiersOnDuty->implode('، ') }}</small>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>النوع</th><th class="text-center">عدد</th><th class="text-end">المبلغ (ج.م)</th></tr>
                            </thead>
                            <tbody>
                                @foreach($feeTypeLabels as $type => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-center">{{ number_format($breakdown['by_fee_type'][$type]['count']) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($breakdown['by_fee_type'][$type]['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="table-light">
                                    <td class="fw-bold">الإجمالي</td>
                                    <td class="text-center fw-bold">{{ number_format($breakdown['count']) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($breakdown['net_total'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payments Table --}}
        <div class="card mb-4">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="ti tabler-list-details me-1"></i>
                    المدفوعات — {{ $window[0] }}
                    @if($reviewMode === 'day')
                        <small class="text-muted">({{ $currentOpenDay && $currentOpenDay->date === $selectedDate && !$currentOpenDay->end_date ? 'مفتوح حتى الآن' : 'مغلق' }})</small>
                    @endif
                </h6>
                <span class="badge bg-label-primary">{{ number_format($breakdown['count']) }} عملية</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>رقم الحافظة</th>
                            <th>الطالب</th>
                            <th>بيان المصروف</th>
                            <th>النوع</th>
                            <th class="text-end">الأصلي</th>
                            <th class="text-end">خصم</th>
                            <th class="text-end">المسدد</th>
                            <th class="text-center">الطريقة</th>
                            <th>الوزاري</th>
                            <th>وقت السداد</th>
                            <th>المحصّل</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->ticket_number }}</td>
                                <td>
                                    <div class="fw-bold">{{ $ticket->student?->name ?? '—' }}</div>
                                    <small class="text-muted">{{ $ticket->student?->username }} / {{ $ticket->student?->level?->name ?? '—' }}</small>
                                </td>
                                <td>{{ $ticket->fee_name ?: ($feeTypeLabels[$ticket->fee_type] ?? $ticket->fee_type) }}</td>
                                <td><span class="badge bg-label-secondary">{{ $feeTypeLabels[$ticket->fee_type] ?? $ticket->fee_type }}</span></td>
                                <td class="text-end">{{ number_format($ticket->grossAmount(), 2) }}</td>
                                <td class="text-end">
                                    @if((float) $ticket->discount_amount > 0)
                                        <span class="text-info">خصم {{ number_format((float) $ticket->discount_amount, 2) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-success">{{ number_format((float) $ticket->amount, 2) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ match($ticket->payment_method) {
                                        'cash' => 'bg-label-success',
                                        'credit' => 'bg-label-info',
                                        'both' => 'bg-label-warning',
                                        default => 'bg-label-secondary',
                                    } }}">
                                        {{ \App\Livewire\Admin\Finance\DailyPayments::PAYMENT_METHODS[$ticket->payment_method] ?? 'غير محدد' }}
                                        @if($ticket->visa_last_four)
                                            <span class="fw-bold">(••••{{ $ticket->visa_last_four }})</span>
                                        @endif
                                    </span>
                                </td>
                                <td>{{ $ticket->ministerial_receipt_number ?? '—' }}</td>
                                <td>{{ $ticket->paid_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $cashiers->get($ticket->id, '—') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="ti tabler-cash-off d-block mb-2" style="font-size: 3rem;"></i>
                                    لا توجد مدفوعات في هذه الفترة
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Wallet Movements --}}
        <div class="card">
            <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="ti tabler-wallet me-1"></i> تحركات المحافظ في الفترة</h6>
                <div class="d-flex gap-2 small">
                    <span class="badge bg-label-success">إيداع: {{ number_format($movementTotals['deposit']['count']) }} / {{ number_format($movementTotals['deposit']['total'], 2) }} ج.م</span>
                    <span class="badge bg-label-danger">سحب: {{ number_format($movementTotals['withdrawal']['count']) }} / {{ number_format($movementTotals['withdrawal']['total'], 2) }} ج.م</span>
                    <span class="badge bg-label-warning">استرداد: {{ number_format($movementTotals['refund']['count']) }} / {{ number_format($movementTotals['refund']['total'], 2) }} ج.م</span>
                </div>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>الطالب</th>
                            <th class="text-center">النوع</th>
                            <th>السبب</th>
                            <th class="text-end">المبلغ (ج.م)</th>
                            <th>بواسطة</th>
                            <th>الوقت</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($movements as $movement)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $movement->student?->name ?? '—' }}</div>
                                    <small class="text-muted">{{ $movement->student?->username }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ match($movement->type) {
                                        \App\Enums\WalletTransactionType::DEPOSIT => 'bg-label-success',
                                        \App\Enums\WalletTransactionType::WITHDRAWAL => 'bg-label-danger',
                                        default => 'bg-label-warning',
                                    } }}">{{ $movement->type->label() }}</span>
                                </td>
                                <td>{{ $movement->reason }}</td>
                                <td class="text-end fw-bold">{{ number_format((float) $movement->amount, 2) }}</td>
                                <td>{{ $movement->performedBy?->name ?? 'نظام' }}</td>
                                <td>{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">لا توجد تحركات محافظ في هذه الفترة</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
