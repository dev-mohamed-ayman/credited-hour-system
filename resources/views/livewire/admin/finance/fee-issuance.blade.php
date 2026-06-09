<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">المالية /</span> إصدار حافظة مصاريف
    </h4>

    <div class="row">
        {{-- Search Card --}}
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <form wire:submit.prevent="searchStudent">
                        <div class="row align-items-end g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="studentCode">كود الطالب</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ti tabler-search"></i></span>
                                    <input type="text" wire:model="studentCode" id="studentCode" class="form-control"
                                        placeholder="أدخل كود الطالب هنا..." autofocus>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti tabler-search me-1"></i> بحث عن الطالب
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if($student)
            {{-- Student Info Card --}}
            <div class="col-md-12 mb-4">
                <div class="card bg-label-primary border-0 shadow-none">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                @if($student->image)
                                    <img src="{{ asset('storage/' . $student->image) }}" class="rounded-circle img-fluid"
                                        style="width: 100px;">
                                @else
                                    <div class="avatar avatar-xl d-inline-block">
                                        <span class="avatar-initial rounded-circle bg-primary fs-2">
                                            {{ mb_substr($student->name, 0, 1) }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-10">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">اسم الطالب</small>
                                        <span class="fw-bold text-heading fs-5">{{ $student->name }}</span>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">الكود</small>
                                        <span class="fw-bold text-heading fs-5">{{ $student->username }}</span>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">الفرقة</small>
                                        <span
                                            class="fw-bold text-heading fs-5">{{ $student->level?->name ?? 'غير محدد' }}</span>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">التخصص / الشعبة</small>
                                        <span class="fw-bold text-heading fs-5">
                                            {{ $student->section?->department?->name }} / {{ $student->section?->name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fees Selection --}}
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">المصاريف المستحقة</h5>
                        <span class="badge bg-label-info">حدد المصاريف المراد إصدار حافظة لها</span>
                    </div>
                    <div class="card-body">
                        @if($additionalFees->isEmpty() && $registrationFees->isEmpty())
                            <div class="alert alert-success text-center py-4">
                                <i class="ti tabler-circle-check fs-1 mb-2"></i>
                                <h5>لا توجد مصاريف مستحقة على هذا الطالب حالياً</h5>
                            </div>
                        @else
                            <form wire:submit.prevent="generateTickets">
                                {{-- Additional Fees Section --}}
                                @if($additionalFees->isNotEmpty())
                                    <div class="mb-4">
                                        <h6 class="text-uppercase text-muted small fw-bold mb-3">1. المصاريف الإدارية والإضافية</h6>
                                        <div class="table-responsive border rounded">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="50">#</th>
                                                        <th>اسم المصروف</th>
                                                        <th class="text-center">المبلغ</th>
                                                        <th width="100" class="text-center">تحديد</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($additionalFees as $fee)
                                                        <tr>
                                                            <td>{{ $loop->iteration }}</td>
                                                            <td>{{ $fee->name }}</td>
                                                            <td class="text-center fw-bold">{{ number_format($fee->amount, 2) }} ج.م
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="checkbox" wire:model="selectedFees"
                                                                    value="additional-{{ $fee->id }}" class="form-check-input">
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                {{-- Registration Fees Section --}}
                                @if($registrationFees->isNotEmpty())
                                    <div class="mb-4">
                                        <h6 class="text-uppercase text-muted small fw-bold mb-3">2. مصاريف التسجيل الدراسي</h6>
                                        <div class="table-responsive border rounded">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="50">#</th>
                                                        <th>الوصف</th>
                                                        <th class="text-center">المبلغ</th>
                                                        <th width="100" class="text-center">تحديد</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($registrationFees as $fee)
                                                        <tr>
                                                            <td>{{ $loop->iteration }}</td>
                                                            <td>مصاريف التسجيل - {{ $student->level?->name }}</td>
                                                            <td class="text-center fw-bold">
                                                                {{ number_format($fee->total_student_payment, 2) }} ج.م</td>
                                                            <td class="text-center">
                                                                <input type="checkbox" wire:model="selectedFees"
                                                                    value="registration-{{ $fee->id }}" class="form-check-input">
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                <div class="mb-4">
                                    <label for="notes" class="form-label">ملاحظات إضافية (تظهر في الوصل)</label>
                                    <textarea wire:model="notes" id="notes" class="form-control" rows="2"
                                        placeholder="أدخل أي ملاحظات هنا..."></textarea>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" wire:click="loadFees" class="btn btn-label-secondary">
                                        <i class="ti tabler-refresh me-1"></i> إعادة تحميل
                                    </button>
                                    <button type="submit" class="btn btn-success px-5">
                                        <i class="ti tabler-printer me-1"></i> إصدار الحافظة والطباعة
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Pending Tickets Section --}}
            @if($pendingTickets && count($pendingTickets) > 0)
                <div class="col-md-12 mt-4">
                    <div class="card border-warning">
                        <div class="card-header bg-label-warning d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">الحافظات الصادرة (غير المدفوعة)</h5>
                            <span class="badge bg-warning text-dark">{{ count($pendingTickets) }} حافظة</span>
                        </div>
                        <div class="card-body pt-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>رقم الحافظة</th>
                                            <th>نوع الرسوم</th>
                                            <th>المبلغ</th>
                                            <th>تاريخ الإصدار</th>
                                            <th class="text-center">إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingTickets as $ticket)
                                            <tr>
                                                <td class="fw-bold">{{ $ticket->ticket_number }}</td>
                                                <td>{{ $ticket->fee_type === 'additional' ? 'رسوم إضافية' : 'رسوم تسجيل' }}</td>
                                                <td class="text-success fw-bold">{{ number_format($ticket->amount, 2) }} ج.م</td>
                                                <td>{{ $ticket->created_at->format('Y-m-d H:i') }}</td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <button type="button" wire:click="printTicket('{{ $ticket->ticket_number }}')" class="btn btn-sm btn-icon btn-label-primary" title="طباعة">
                                                            <i class="ti tabler-printer"></i>
                                                        </button>
                                                        <button type="button" wire:confirm="هل أنت متأكد من حذف هذه الحافظة؟" wire:click="deleteTicket({{ $ticket->id }})" class="btn btn-sm btn-icon btn-label-danger" title="حذف">
                                                            <i class="ti tabler-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    @script
    <script>
        $wire.on('alert', (data) => {
            const [payload] = data;
            // You can use Toastr or SweetAlert2 here if available in the project
            alert(payload.message);
        });
    </script>
    @endscript
</div>