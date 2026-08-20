<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">المالية /</span> اليوميات المالية
    </h4>

    <div class="row">
        {{-- Current Day Status Card --}}
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">حالة اليوم الحالي</h5>
                    @if($currentOpenDay)
                        <span class="badge bg-success">يوم مفتوح</span>
                    @else
                        <span class="badge bg-danger">لا يوجد يوم مفتوح</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($currentOpenDay)
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>تاريخ اليوم:</strong> {{ $currentOpenDay->date }}</p>
                                <p><strong>وقت الفتح:</strong> {{ $currentOpenDay->start_date }}</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <button wire:click="closeDay" class="btn btn-danger">
                                    <i class="ti tabler-lock me-1"></i> غلق اليوم
                                </button>
                            </div>
                        </div>
                    @else
                        <form wire:submit.prevent="openDay">
                            <div class="row align-items-end g-3">
                                <div class="col-md-8">
                                    <label class="form-label" for="selectedDate">تاريخ اليوم</label>
                                    <div class="input-group input-group-merge">
                                        <span class="input-group-text"><i class="ti tabler-calendar"></i></span>
                                        <input type="date" wire:model="selectedDate" id="selectedDate" class="form-control">
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
        </div>

        {{-- Daily Payments History --}}
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">سجل اليوميات</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">اختر يوم لعرض الحوافظ</label>
                        <select wire:model.live="selectedDate" class="form-select">
                            @foreach($days as $day)
                                <option value="{{ $day->date }}">
                                    {{ $day->date }}
                                    @if($day->end_date)
                                        (مغلق)
                                    @else
                                        (مفتوح)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($selectedDay)
                        <hr>
                        <h6 class="mb-3">
                            الحوافظ المسددة في يوم {{ $selectedDay->date }}
                            <span class="badge bg-label-info ms-2">{{ $tickets->count() }} حافظة</span>
                        </h6>

                        @if($tickets->count() > 0)
                            @php
                                $totalCash = $tickets->where('payment_method', 'cash')->sum('amount');
                                $totalCredit = $tickets->where('payment_method', 'credit')->sum('amount');
                                $totalBoth = $tickets->where('payment_method', 'both')->sum('amount');
                                $totalAmount = $tickets->sum('amount');
                            @endphp

                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="text-center">
                                                <h6 class="text-muted mb-2">إجمالي الكاش</h6>
                                                <h4 class="text-primary">{{ number_format($totalCash, 2) }} ج.م</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="text-center">
                                                <h6 class="text-muted mb-2">إجمالي الفيزا</h6>
                                                <h4 class="text-info">{{ number_format($totalCredit, 2) }} ج.م</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="text-center">
                                                <h6 class="text-muted mb-2">إجمالي كاش وفيزا</h6>
                                                <h4 class="text-warning">{{ number_format($totalBoth, 2) }} ج.م</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="text-center">
                                                <h6 class="text-muted mb-2">الإجمالي الكل</h6>
                                                <h4 class="text-success">{{ number_format($totalAmount, 2) }} ج.م</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>رقم الحافظة</th>
                                        <th>اسم الطالب</th>
                                        <th>نوع الرسوم</th>
                                        <th>المبلغ</th>
                                        <th>طريقة الدفع</th>
                                        <th>وقت السداد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tickets as $ticket)
                                        <tr>
                                            <td>{{ $ticket->ticket_number }}</td>
                                            <td>{{ $ticket->student->name ?? 'N/A' }}</td>
                                            <td>{{ $ticket->fee_name ?? match($ticket->fee_type) {
                                                'additional' => 'رسوم إضافية',
                                                'military_education' => 'مصاريف التربية العسكرية',
                                                'other' => 'مصاريف أخرى',
                                                default => 'رسوم تسجيل',
                                            } }}</td>
                                            <td>{{ number_format($ticket->amount, 2) }} ج.م</td>
                                            <td>
                                                <span class="badge {{ match($ticket->payment_method) {
                                                    'cash' => 'bg-label-success',
                                                    'credit' => 'bg-label-info',
                                                    'both' => 'bg-label-warning',
                                                    default => 'bg-label-secondary',
                                                } }}">
                                                    {{ match($ticket->payment_method) {
                                                        'cash' => 'كاش',
                                                        'credit' => 'فيزا',
                                                        'both' => 'كاش وفيزا',
                                                        default => 'N/A',
                                                    } }}
                                                </span>
                                            </td>
                                            <td>{{ $ticket->paid_at }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="text-center py-4">
                                <p class="text-muted">لا توجد حوافظ مسددة في هذا اليوم</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
