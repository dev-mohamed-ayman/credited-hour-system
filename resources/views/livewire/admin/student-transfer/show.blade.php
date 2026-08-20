@php
    $isPending = $transferRequest->isPending();
    $isApproved = $transferRequest->status === \App\Enums\TransferRequestStatus::APPROVED;
@endphp

<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">تفاصيل طلب تحويل التخصص</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('student-transfers.index') }}">طلبات تحويل التخصص</a></li>
                    <li class="breadcrumb-item active">طلب #{{ $transferRequest->id }}</li>
                </ol>
            </nav>
        </div>
        <span class="badge {{ $transferRequest->status->badgeClass() }} fs-6">{{ $transferRequest->status->label() }}</span>
    </div>

    {{-- الطالب ومسار التحويل --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-4 align-items-center">
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3">
                            <span class="avatar-initial rounded-circle bg-label-primary"><i class="ti tabler-user"></i></span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $transferRequest->student?->name ?? '—' }}</h6>
                            <small class="text-muted">{{ $transferRequest->student?->username }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block mb-1">من</small>
                    <h6 class="mb-0">{{ $transferRequest->fromDepartment?->name ?? '—' }}</h6>
                    <small class="text-muted">{{ $transferRequest->fromSection?->name }} — {{ $transferRequest->fromLevel?->name }}</small>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block mb-1">إلى</small>
                    <h6 class="mb-0 text-primary">{{ $transferRequest->toDepartment?->name ?? '—' }}</h6>
                    <small class="text-muted">{{ $transferRequest->toSection?->name }} — {{ $transferRequest->toLevel?->name }}</small>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3 small">
                <div class="col-md-3">
                    <span class="text-muted d-block">الترم المتأثر</span>
                    <strong>{{ $transferRequest->year?->year ?? '—' }} / {{ $transferRequest->semester?->label() ?? '—' }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">أنشأ الطلب</span>
                    <strong>{{ $transferRequest->createdByUser?->name ?? '—' }}</strong>
                    <span class="text-muted">({{ $transferRequest->created_at->format('Y-m-d') }})</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">بتّ في الطلب</span>
                    <strong>{{ $transferRequest->decidedByUser?->name ?? '—' }}</strong>
                    @if($transferRequest->decided_at)
                        <span class="text-muted">({{ $transferRequest->decided_at->format('Y-m-d') }})</span>
                    @endif
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">سبب التحويل</span>
                    <strong>{{ $transferRequest->reason ?: '—' }}</strong>
                </div>
            </div>

            @if($transferRequest->rejection_reason)
                <div class="alert alert-danger mt-4 mb-0" role="alert">
                    <strong>سبب الرفض:</strong> {{ $transferRequest->rejection_reason }}
                </div>
            @endif
        </div>
    </div>

    @if($isPending && ! empty($details['warnings']))
        <div class="alert alert-warning" role="alert">
            <h6 class="alert-heading mb-2"><i class="ti tabler-alert-triangle me-1"></i> تنبيهات قبل الموافقة</h6>
            <ul class="mb-0 ps-3">
                @foreach($details['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ملخص الاسترجاع --}}
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">استرداد رسوم المواد</small>
                    <h5 class="mb-0">{{ number_format((float) $details['registration_refund'], 2) }} ج.م</h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">استرداد الحوافظ المدفوعة</small>
                    <h5 class="mb-0">{{ number_format((float) $details['ticket_refund'], 2) }} ج.م</h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-primary">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">{{ $isApproved ? 'إجمالي ما تم استرداده' : 'إجمالي ما سيتم استرداده' }}</small>
                    <h5 class="mb-0 text-primary">{{ number_format((float) $details['total_refund'], 2) }} ج.م</h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-1">رصيد المحفظة</small>
                    <h5 class="mb-0">
                        {{ number_format((float) $details['wallet_balance_before'], 2) }}
                        <i class="ti tabler-arrow-left mx-1 text-muted"></i>
                        <span class="text-success">{{ number_format((float) $details['wallet_balance_after'], 2) }}</span>
                    </h5>
                </div>
            </div>
        </div>
    </div>

    {{-- مقارنة رسوم التخصصين --}}
    <div class="card mb-4">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">مقارنة مصاريف التسجيل</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th></th>
                        <th class="text-center">سعر الساعة</th>
                        <th class="text-center">الرسوم الوزارية</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $transferRequest->fromDepartment?->name ?? 'التخصص الحالي' }}</td>
                        <td class="text-center">{{ $details['from_fee_setting'] ? number_format((float) $details['from_fee_setting']['hour_payment'], 2).' ج.م' : '— غير مُعرّفة' }}</td>
                        <td class="text-center">{{ $details['from_fee_setting'] ? number_format((float) $details['from_fee_setting']['ministerial_payment'], 2).' ج.م' : '—' }}</td>
                    </tr>
                    <tr class="table-active">
                        <td class="fw-bold">{{ $transferRequest->toDepartment?->name ?? 'التخصص الجديد' }}</td>
                        <td class="text-center fw-bold">{{ $details['to_fee_setting'] ? number_format((float) $details['to_fee_setting']['hour_payment'], 2).' ج.م' : '— غير مُعرّفة' }}</td>
                        <td class="text-center fw-bold">{{ $details['to_fee_setting'] ? number_format((float) $details['to_fee_setting']['ministerial_payment'], 2).' ج.م' : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- المواد --}}
    <div class="card mb-4">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ $isApproved ? 'المواد التي تم إلغاء تسجيلها' : 'المواد التي سيتم إلغاء تسجيلها' }}</h5>
            <span class="badge bg-label-info">{{ count($details['registrations']) }} سجل</span>
        </div>
        <div class="card-body">
            @forelse($details['registrations'] as $registration)
                <div class="border rounded p-3 {{ ! $loop->last ? 'mb-3' : '' }}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong>سجل #{{ $registration['id'] }}</strong>
                            <span class="text-muted small ms-2">{{ $registration['semester'] }} — {{ $registration['status'] }}</span>
                        </div>
                        <span class="badge bg-label-warning">مخصوم: {{ number_format((float) $registration['charged_amount'], 2) }} ج.م</span>
                    </div>
                    <div class="table-responsive text-nowrap">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الكود</th>
                                    <th>المادة</th>
                                    <th class="text-center">الساعات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registration['courses'] as $course)
                                    <tr>
                                        <td>{{ $course['code'] }}</td>
                                        <td>{{ $course['name'] }}</td>
                                        <td class="text-center">{{ $course['hours'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-muted">لا توجد مواد مسجلة في هذا الترم</div>
            @endforelse
        </div>
    </div>

    {{-- الحوافظ --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">{{ $isApproved ? 'حوافظ مدفوعة تم استردادها' : 'حوافظ مدفوعة سيتم استردادها' }}</h5>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>رقم الحافظة</th>
                                <th>البيان</th>
                                <th class="text-center">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($details['paid_tickets'] as $ticket)
                                <tr>
                                    <td>{{ $ticket['ticket_number'] }}</td>
                                    <td>{{ $ticket['fee_name'] }}</td>
                                    <td class="text-center">{{ number_format((float) $ticket['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4 text-muted">لا توجد حوافظ مدفوعة</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">{{ $isApproved ? 'حوافظ غير مدفوعة تم إلغاؤها' : 'حوافظ غير مدفوعة سيتم إلغاؤها' }}</h5>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>رقم الحافظة</th>
                                <th>البيان</th>
                                <th class="text-center">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($details['pending_tickets'] as $ticket)
                                <tr>
                                    <td>{{ $ticket['ticket_number'] }}</td>
                                    <td>{{ $ticket['fee_name'] }}</td>
                                    <td class="text-center">{{ number_format((float) $ticket['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4 text-muted">لا توجد حوافظ غير مدفوعة</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- القرار --}}
    @if($isPending)
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">القرار</h5>
            </div>
            <div class="card-body">
                @canany(['student_transfers.approve', 'student_transfers.reject'])
                    <div class="alert alert-info" role="alert">
                        <i class="ti tabler-info-circle me-1"></i>
                        الموافقة ستُلغي المواد والحوافظ أعلاه وتُعيد <strong>{{ number_format((float) $details['total_refund'], 2) }} ج.م</strong>
                        إلى محفظة الطالب، ثم تنقله للتخصص الجديد. لا يمكن التراجع عن هذه الخطوة.
                    </div>

                    @can('student_transfers.reject')
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label fw-bold">سبب الرفض</label>
                            <textarea wire:model="rejectionReason" id="rejectionReason" rows="2"
                                      class="form-control @error('rejectionReason') is-invalid @enderror"
                                      placeholder="مطلوب عند رفض الطلب..."></textarea>
                            @error('rejectionReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endcan

                    <div class="d-flex gap-2">
                        @can('student_transfers.approve')
                            <button type="button" class="btn btn-success" wire:click="approve" wire:loading.attr="disabled"
                                    wire:confirm="سيتم استرجاع المصاريف وإلغاء تسجيل المواد ونقل الطالب. هل أنت متأكد؟">
                                <span wire:loading.remove wire:target="approve"><i class="ti tabler-check me-1"></i> موافقة وتنفيذ التحويل</span>
                                <span wire:loading wire:target="approve">جارٍ التنفيذ...</span>
                            </button>
                        @endcan
                        @can('student_transfers.reject')
                            <button type="button" class="btn btn-label-danger" wire:click="reject" wire:loading.attr="disabled">
                                <i class="ti tabler-x me-1"></i> رفض الطلب
                            </button>
                        @endcan
                    </div>
                @else
                    <div class="text-muted">ليس لديك صلاحية البت في طلبات التحويل.</div>
                @endcanany
            </div>
        </div>
    @endif
</div>
