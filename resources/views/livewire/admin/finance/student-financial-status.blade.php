<div>
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-label-secondary btn-icon rounded-circle">
                <i class="ti tabler-arrow-right"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-heading">بيان الحالة المالية للطالب</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="#">المالية</a></li>
                        <li class="breadcrumb-item active">بيان الحالة</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Search Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0"><i class="ti tabler-search me-2"></i>البحث عن طالب</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="form-group">
                        <input 
                            type="text" 
                            class="form-control form-control-lg" 
                            placeholder="البحث باسم الطالب أو الكود..." 
                            wire:model.live="searchQuery"
                            wire:keydown.enter="searchStudent"
                        >
                        @if($recentStudents->isNotEmpty() && !$student)
                            <div class="card mt-2 shadow-sm border border-light">
                                <div class="list-group list-group-flush">
                                    @foreach($recentStudents as $s)
                                        <button 
                                            type="button" 
                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                            wire:click="selectStudent({{ $s->id }})"
                                        >
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar avatar-sm">
                                                    <span class="avatar-initial rounded-circle bg-label-primary">
                                                        {{ mb_substr($s->name, 0, 1) }}
                                                    </span>
                                                </div>
                                                <div class="text-start">
                                                    <div class="fw-medium">{{ $s->name }}</div>
                                                    <small class="text-muted">{{ $s->username }}</small>
                                                </div>
                                            </div>
                                            <i class="ti tabler-chevron-left text-muted"></i>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button 
                        class="btn btn-primary btn-lg flex-1" 
                        wire:click="searchStudent"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="searchStudent"><i class="ti tabler-search me-1"></i> بحث</span>
                        <span wire:loading wire:target="searchStudent"><i class="ti tabler-loader-2 spin me-1"></i> جاري البحث...</span>
                    </button>
                    @if($student)
                        <button 
                            class="btn btn-label-secondary btn-lg" 
                            wire:click="clearSearch"
                        >
                            <i class="ti tabler-x"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Student Status -->
    @if($student)
        <!-- Student Info & Summary -->
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0"><i class="ti tabler-user me-2"></i>بيانات الطالب</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="avatar avatar-xl">
                                <span class="avatar-initial rounded-circle bg-label-primary fs-2">
                                    {{ mb_substr($student->name, 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <h5 class="mb-1">{{ $student->name }}</h5>
                                <span class="badge bg-label-secondary">{{ $student->username }}</span>
                            </div>
                        </div>
                        <div class="info-item mb-2">
                            <span class="fw-medium text-muted">الفرقة:</span>
                            <span class="ms-2">{{ $student->level?->name ?? 'غير محدد' }}</span>
                        </div>
                        <div class="info-item mb-2">
                            <span class="fw-medium text-muted">الشعبة:</span>
                            <span class="ms-2">{{ $student->section?->name ?? 'غير محدد' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="fw-medium text-muted">القسم:</span>
                            <span class="ms-2">{{ $student->section?->department?->name ?? 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-8 col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0"><i class="ti tabler-chart-pie me-2"></i>ملخص الحالة المالية</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 bg-label-primary rounded border-start border-primary border-4">
                                    <small class="text-muted d-block">إجمالي الرسوم</small>
                                    <span class="fw-bold text-heading fs-5">{{ number_format($totalFees, 2) }} ج</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-label-success rounded border-start border-success border-4">
                                    <small class="text-muted d-block">المبلغ المدفوع</small>
                                    <span class="fw-bold text-heading fs-5">{{ number_format($totalPaid, 2) }} ج</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-label-warning rounded border-start {{ $remaining > 0 ? 'border-warning' : 'border-success' }} border-4">
                                    <small class="text-muted d-block">المتبقي</small>
                                    <span class="fw-bold text-heading fs-5">{{ number_format($remaining, 2) }} ج</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fee Tickets -->
        @if($student->feeTickets && $student->feeTickets->count())
            @php
                $yearGroups = $student->feeTickets->groupBy('year_id');
            @endphp
            <div id="financialRecordsAccordion">
                @foreach($yearGroups as $yearId => $yearTickets)
                    @php
                        $year = $yearTickets->first()->year;
                        $yearTotal = $yearTickets->sum('amount');
                        $yearPaid = $yearTickets->sum('paid');
                        $yearRemaining = $yearTotal - $yearPaid;
                    @endphp
                    <div class="card border-0 shadow-sm mb-3 overflow-hidden">
                        <div class="card-header border-bottom bg-transparent d-flex justify-content-between align-items-center p-0" id="headingYear{{ $yearId }}">
                            <button 
                                class="accordion-button d-flex w-100 justify-content-between align-items-center p-4 text-start text-decoration-none"
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#collapseYear{{ $yearId }}" 
                                aria-expanded="true" 
                                aria-controls="collapseYear{{ $yearId }}"
                            >
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar avatar-md">
                                        <div class="avatar-initial bg-label-primary rounded-circle"><i class="ti tabler-calendar-star fs-4"></i></div>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-heading fs-5">
                                            {{ $year?->year ?? 'سنة غير محددة' }}
                                        </span>
                                        <span class="badge bg-label-primary ms-2">
                                            {{ $yearTickets->count() }} حافظة
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="text-muted small">
                                        <i class="ti tabler-cash me-1"></i>
                                        الإجمالي: <span class="fw-bold text-primary">{{ number_format($yearTotal, 2) }} ج</span>
                                    </span>
                                    <span class="badge {{ $yearRemaining > 0 ? 'bg-label-warning' : 'bg-label-success' }}">
                                        المتبقي: {{ number_format($yearRemaining, 2) }} ج
                                    </span>
                                </div>
                            </button>
                        </div>
                        <div 
                            id="collapseYear{{ $yearId }}" 
                            class="collapse show" 
                            aria-labelledby="headingYear{{ $yearId }}"
                        >
                            <div class="card-body p-4 border-top">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="py-3">التفاصيل</th>
                                                <th class="text-center py-3">التاريخ</th>
                                                <th class="text-center py-3">الإجمالي</th>
                                                <th class="text-center py-3">المدفوع</th>
                                                <th class="text-center py-3">المتبقي</th>
                                                <th class="text-center py-3">الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            @foreach($yearTickets as $ticket)
                                                @php
                                                    $ticketRemaining = $ticket->amount - $ticket->paid;
                                                @endphp
                                                <tr>
                                                    <td class="fw-medium text-heading">
                                                        {{ $ticket->notes ?? 'رسوم دراسية' }}
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $ticket->created_at?->format('Y-m-d') ?? '-' }}
                                                    </td>
                                                    <td class="text-center fw-medium">
                                                        {{ number_format($ticket->amount, 2) }} ج
                                                    </td>
                                                    <td class="text-center fw-medium text-success">
                                                        {{ number_format($ticket->paid, 2) }} ج
                                                    </td>
                                                    <td class="text-center fw-medium {{ $ticketRemaining > 0 ? 'text-warning' : 'text-success' }}">
                                                        {{ number_format($ticketRemaining, 2) }} ج
                                                    </td>
                                                    <td class="text-center">
                                                        @if($ticketRemaining <= 0)
                                                            <span class="badge bg-label-success">مسدد بالكامل</span>
                                                        @elseif($ticket->paid > 0)
                                                            <span class="badge bg-label-warning">مسدد جزئياً</span>
                                                        @else
                                                            <span class="badge bg-label-danger">غير مسدد</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="text-muted">
                        <i class="ti tabler-file-invoice d-block mb-3 fs-1"></i>
                        <h5 class="mb-2">لا توجد حافظات مالية لهذا الطالب حتى الآن</h5>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="text-muted">
                    <i class="ti tabler-search d-block mb-3 fs-1"></i>
                    <h5 class="mb-2">ابحث عن طالب لعرض بيان حالته</h5>
                    <p class="mb-0">استخدم مربع البحث أعلاه للعثور على الطالب وعرض تفاصيل حالته المالية</p>
                </div>
            </div>
        </div>
    @endif
</div>
