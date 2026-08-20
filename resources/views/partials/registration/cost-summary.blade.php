{{-- Live cost of the current selection, so nobody commits to a charge they cannot see. --}}
@if ($quote)
    <div class="card mb-4">
        <div class="card-header border-bottom d-flex align-items-center">
            <span class="avatar avatar-sm me-2">
                <span class="avatar-initial rounded-circle bg-label-primary">
                    <i class="ti tabler-calculator"></i>
                </span>
            </span>
            <div>
                <h5 class="card-title mb-0">تكلفة التسجيل</h5>
                <small class="text-muted">تتحدّث تلقائياً مع كل مادة تختارها</small>
            </div>
        </div>
        <div class="card-body">
            @if (! $quote['has_fee_setting'])
                <div class="alert alert-warning mb-0 py-2">
                    <i class="ti tabler-alert-triangle me-1"></i>
                    لم يتم ضبط رسوم التسجيل لهذا التخصص والفرقة، لذلك لن يتم خصم أي مبلغ.
                </div>
            @else
                <div class="row g-3">
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <p class="text-muted mb-1 small">الساعات المختارة</p>
                            <h5 class="mb-0 fw-bold">
                                {{ $quote['hours'] }}
                                @if ($quote['existing_hours'] > 0)
                                    <small class="text-muted fw-normal">+ {{ $quote['existing_hours'] }} مسجّلة</small>
                                @endif
                            </h5>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <p class="text-muted mb-1 small">سعر الساعة</p>
                            <h5 class="mb-0 fw-bold">{{ number_format($quote['hour_payment'], 2) }} ج.م</h5>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <p class="text-muted mb-1 small">الرسوم الوزارية</p>
                            <h5 class="mb-0 fw-bold">{{ number_format($quote['ministerial_payment'], 2) }} ج.م</h5>
                            <small class="text-muted">تُحتسب مرة واحدة للترم</small>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100 bg-label-primary">
                            <p class="mb-1 small">المطلوب خصمه الآن</p>
                            <h5 class="mb-0 fw-bold">{{ number_format(max($quote['delta'], 0), 2) }} ج.م</h5>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted">رصيد المحفظة الحالي</span>
                            <span class="fw-bold">{{ number_format($quote['balance'], 2) }} ج.م</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted">الرصيد بعد الخصم</span>
                            <span class="fw-bold {{ $quote['balance_after'] < 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($quote['balance_after'], 2) }} ج.م
                            </span>
                        </div>
                    </div>
                </div>

                @if ($quote['balance_after'] < 0)
                    <div class="alert alert-danger mb-0 mt-3 py-2">
                        <i class="ti tabler-alert-circle me-1"></i>
                        الرصيد لا يغطي تكلفة المواد المختارة. العجز
                        <strong>{{ number_format(abs($quote['balance_after']), 2) }} ج.م</strong>.
                    </div>
                @endif
            @endif
        </div>
    </div>
@endif
