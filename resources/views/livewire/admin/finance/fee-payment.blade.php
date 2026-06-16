<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">المالية /</span> سداد الحافظة
    </h4>

    <div class="row">
        {{-- Search Card --}}
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <form wire:submit.prevent="searchTicket">
                        <div class="row align-items-end g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="ticketNumber">رقم الحافظة</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ti tabler-search"></i></span>
                                    <input type="text" wire:model="ticketNumber" id="ticketNumber" class="form-control"
                                        placeholder="أدخل رقم الحافظة هنا..." autofocus>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti tabler-search me-1"></i> بحث عن الحافظة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if($showForm && count($tickets) > 0)
            @php
                $firstTicket = $tickets[0];
                $hasRegistrationFees = collect($tickets)->contains(fn($t) => $t->fee_type === 'registration');
                $totalAmount = collect($tickets)->sum('amount');
            @endphp
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">تفاصيل الحوافظ والسداد</h5>
                        <span class="badge bg-label-info">{{ count($tickets) }} حافظة</span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>اسم الطالب:</strong> {{ $firstTicket->student->name }}</p>
                                <p><strong>كود الطالب:</strong> {{ $firstTicket->student->username }}</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h4 class="text-primary"><strong>المبلغ الإجمالي:</strong> {{ number_format($totalAmount, 2) }} ج.م</h4>
                            </div>
                        </div>

                        <table class="table table-bordered mb-4">
                            <thead>
                                <tr>
                                    <th>رقم الحافظة</th>
                                    <th>نوع الرسوم</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tickets as $ticket)
                                    <tr>
                                        <td>{{ $ticket->ticket_number }}</td>
                                        <td>{{ $ticket->fee_type === 'additional' ? 'رسوم إضافية' : 'رسوم تسجيل' }}</td>
                                        <td>{{ number_format($ticket->amount, 2) }} ج.م</td>
                                        <td>
                                            <span class="badge {{ $ticket->status === 'paid' ? 'bg-success' : 'bg-warning' }}">
                                                {{ $ticket->status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <hr class="my-4">

                        <form wire:submit.prevent="confirmPayment">
                            <div class="row g-3">
                                @if($hasRegistrationFees)
                                    <div class="col-md-6">
                                        <label class="form-label">رقم الإيصال الوزاري</label>
                                        <input type="text" class="form-control bg-light" value="{{ $ministerialReceiptNumber }}" disabled>
                                        <small class="text-muted">يتم التوليد تلقائياً من الإعدادات</small>
                                    </div>
                                @endif

                                <div class="col-md-{{ $hasRegistrationFees ? '6' : '12' }}">
                                    <label class="form-label">نوع السداد</label>
                                    <select wire:model.live="paymentMethod" class="form-select">
                                        <option value="cash">كاش</option>
                                        <option value="credit">فيزا (كريديت)</option>
                                        <option value="both">كاش وفيزا معاً</option>
                                    </select>
                                </div>

                                @if($paymentMethod !== 'cash')
                                    <div class="col-md-6">
                                        <label class="form-label">آخر 4 أرقام من الفيزا</label>
                                        <input type="text" wire:model="visaLastFour" class="form-control" maxlength="4" placeholder="مثال: 1234">
                                        @error('visaLastFour') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>
                                @endif

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="ti tabler-check me-1"></i> تأكيد سداد {{ count($tickets) }} حافظة
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
