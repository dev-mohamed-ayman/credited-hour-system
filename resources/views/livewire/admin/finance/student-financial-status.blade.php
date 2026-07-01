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
                                            <div class="d-flex align-items-center gap-2">
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
                        <span wire:loading.remove><i class="ti tabler-search me-1"></i> بحث</span>
                        <span wire:loading><i class="ti tabler-loader-2 spin me-1"></i> جاري البحث...</span>
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

    <!-- Student Financial Status -->
    @if($student)
        <!-- Student Info & Summary -->
        <div class="row g-4 mb-4">
            <!-- Student Profile -->
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm h-100">
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
                        <div class="info-container">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <span class="fw-medium me-1">الفرقة:</span>
                                    <span>{{ $student->level?->name ?? 'غير محدد' }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="fw-medium me-1">الشعبة:</span>
                                    <span>{{ $student->section?->name ?? 'غير محدد' }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="fw-medium me-1">القسم:</span>
                                    <span>{{ $student->section?->department?->name ?? 'غير محدد' }}</span>
                                </li>
                                <li class="mb-2">
                                    <span class="fw-medium me-1">السنة الدراسية:</span>
                                    <span>{{ $student->year?->year ?? 'غير محدد' }}</span>
                                </li>
                                <li>
                                    <span class="fw-medium me-1">الحالة:</span>
                                    @php
                                        $statusClass = match($student->status?->value) {
                                            'active' => 'bg-label-success',
                                            'inactive' => 'bg-label-danger',
                                            'suspended' => 'bg-label-warning',
                                            default => 'bg-label-secondary'
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }}">{{ $student->status?->label() ?? 'غير محدد' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0"><i class="ti tabler-chart-pie me-2"></i>ملخص الحالة المالية</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card bg-label-info h-100 border-0">
                                    <div class="card-body text-center">
                                        <div class="avatar avatar-xl mb-3 mx-auto">
                                            <div class="avatar-initial rounded-circle bg-info">
                                                <i class="ti tabler-cash fs-3"></i>
                                            </div>
                                        </div>
                                        <h3 class="mb-1 fw-bold text-info">{{ number_format($totalFees, 2) }} ج</h3>
                                        <small class="text-info fw-medium">إجمالي المصاريف</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-label-success h-100 border-0">
                                    <div class="card-body text-center">
                                        <div class="avatar avatar-xl mb-3 mx-auto">
                                            <div class="avatar-initial rounded-circle bg-success">
                                                <i class="ti tabler-circle-check fs-3"></i>
                                            </div>
                                        </div>
                                        <h3 class="mb-1 fw-bold text-success">{{ number_format($totalPaid, 2) }} ج</h3>
                                        <small class="text-success fw-medium">المصاريف المسددة</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-label-warning h-100 border-0">
                                    <div class="card-body text-center">
                                        <div class="avatar avatar-xl mb-3 mx-auto">
                                            <div class="avatar-initial rounded-circle bg-warning">
                                                <i class="ti tabler-clock fs-3"></i>
                                            </div>
                                        </div>
                                        <h3 class="mb-1 fw-bold text-warning">{{ number_format($totalPending, 2) }} ج</h3>
                                        <small class="text-warning fw-medium">المصاريف المتبقية</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fee Tickets -->
        @if($groupedTickets->isNotEmpty())
            @foreach($groupedTickets as $period => $tickets)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="ti tabler-calendar-month me-2"></i>
                            {{ $period }}
                        </h5>
                        <span class="badge bg-label-primary">{{ $tickets->count() }} حافظة</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3">رقم الحافظة</th>
                                        <th class="py-3">نوع المصروف</th>
                                        <th class="py-3">الاسم</th>
                                        <th class="text-center py-3">المبلغ</th>
                                        <th class="text-center py-3">الحالة</th>
                                        <th class="text-center py-3">تاريخ الإنشاء</th>
                                        <th class="text-center py-3">تاريخ السداد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tickets as $ticket)
                                        <tr>
                                            <td>
                                                <span class="fw-medium">{{ $ticket->ticket_number }}</span>
                                            </td>
                                            <td>
                                                @php
                                                    $typeColors = [
                                                        'registration' => 'bg-label-primary',
                                                        'additional' => 'bg-label-info',
                                                        'military_education' => 'bg-label-warning',
                                                        'other' => 'bg-label-secondary'
                                                    ];
                                                    $typeLabels = [
                                                        'registration' => 'مصاريف تسجيل',
                                                        'additional' => 'مصاريف إضافية',
                                                        'military_education' => 'تربية عسكرية',
                                                        'other' => 'مصاريف أخرى'
                                                    ];
                                                @endphp
                                                <span class="badge {{ $typeColors[$ticket->fee_type] ?? 'bg-label-secondary' }}">
                                                    {{ $typeLabels[$ticket->fee_type] ?? $ticket->fee_type }}
                                                </span>
                                            </td>
                                            <td class="fw-medium text-heading">
                                                {{ $ticket->fee_name }}
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-bold fs-5 text-primary">{{ number_format($ticket->amount, 2) }}</span>
                                                <small class="text-muted d-block">جنيه</small>
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $ticketStatusClass = match($ticket->status) {
                                                        'paid' => 'bg-label-success',
                                                        'pending' => 'bg-label-warning',
                                                        'cancelled' => 'bg-label-danger',
                                                        default => 'bg-label-secondary'
                                                    };
                                                    $ticketStatusLabels = [
                                                        'paid' => 'مسدد',
                                                        'pending' => 'غير مسدد',
                                                        'cancelled' => 'ملغى',
                                                    ];
                                                @endphp
                                                <span class="badge {{ $ticketStatusClass }}">
                                                    {{ $ticketStatusLabels[$ticket->status] ?? $ticket->status }}
                                                </span>
                                                @if($ticket->status === 'paid' && $ticket->ministerial_receipt_number)
                                                    <small class="d-block text-muted mt-1">
                                                        {{ $ticket->ministerial_receipt_number }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td class="text-center text-muted">
                                                {{ $ticket->created_at?->format('Y-m-d') ?? '-' }}
                                            </td>
                                            <td class="text-center text-muted">
                                                {{ $ticket->paid_at?->format('Y-m-d') ?? '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="text-muted">
                        <i class="ti tabler-file-invoice d-block mb-3 fs-1"></i>
                        <h5 class="mb-2">لا توجد حافظات مسجلة لهذا الطالب</h5>
                        <p class="mb-0">لم يتم إصدار أي حافظات للطالب حتى الآن</p>
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="text-muted">
                    <i class="ti tabler-search d-block mb-3 fs-1"></i>
                    <h5 class="mb-2">ابحث عن طالب لعرض حالته المالية</h5>
                    <p class="mb-0">استخدم مربع البحث أعلاه للعثور على الطالب وعرض تفاصيل حالته المالية</p>
                </div>
            </div>
        </div>
    @endif
</div>
