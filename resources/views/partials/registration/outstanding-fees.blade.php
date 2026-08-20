{{-- Blocks registration while the student still owes money, and says exactly what is owed. --}}
@if ($tickets->isNotEmpty())
    <div class="card border border-danger mb-4">
        <div class="card-header border-bottom d-flex align-items-center">
            <span class="avatar avatar-sm me-2">
                <span class="avatar-initial rounded-circle bg-label-danger">
                    <i class="ti tabler-cash-off"></i>
                </span>
            </span>
            <div>
                <h5 class="card-title mb-0 text-danger">التسجيل موقوف — مصاريف غير مدفوعة</h5>
                <small class="text-muted">لا يمكن تسجيل أي مواد قبل سداد الحوافظ التالية</small>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>رقم الحافظة</th>
                            <th>البيان</th>
                            <th>العام / الترم</th>
                            <th class="text-end">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td><code class="text-body">{{ $ticket->ticket_number }}</code></td>
                                <td>{{ $ticket->fee_name ?? '—' }}</td>
                                <td class="text-muted">
                                    {{ $ticket->year?->year ?? '—' }}
                                    @if ($ticket->semester)
                                        <span class="mx-1">/</span>{{ $ticket->semester->label() }}
                                    @endif
                                </td>
                                <td class="text-end fw-bold">{{ number_format((float) $ticket->amount, 2) }} ج.م</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-top">
                        <tr>
                            <th colspan="3" class="text-end">الإجمالي المستحق</th>
                            <th class="text-end text-danger fs-5">
                                {{ number_format((float) $tickets->sum('amount'), 2) }} ج.م
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="alert alert-danger mb-0 mt-3 py-2">
                <i class="ti tabler-info-circle me-1"></i>
                برجاء التوجه إلى الشؤون المالية للسداد، وسيُفتح التسجيل تلقائياً بعد تسجيل السداد.
            </div>
        </div>
    </div>
@endif
