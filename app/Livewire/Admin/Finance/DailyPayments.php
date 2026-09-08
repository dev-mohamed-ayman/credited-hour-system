<?php

namespace App\Livewire\Admin\Finance;

use App\Enums\WalletTransactionType;
use App\Models\DailyPaymentDateTime;
use App\Models\StudentFeeTicket;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyPayments extends Component
{
    public const PAYMENT_METHODS = [
        'cash' => 'نقدي (كاش)',
        'credit' => 'فيزا',
        'both' => 'نقدي وفيزا',
    ];

    public string $selectedDate = '';

    public ?DailyPaymentDateTime $currentOpenDay = null;

    public string $reviewMode = 'day';

    public string $rangeFrom = '';

    public string $rangeTo = '';

    public function mount(): void
    {
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentOpenDay = DailyPaymentDateTime::whereNull('end_date')->first();
    }

    public function openDay(): void
    {
        abort_unless(auth()->user()->can('finance.edit'), 403);

        $this->validate([
            'selectedDate' => 'required|date|unique:daily_payments_datetime,date',
        ]);

        if ($this->currentOpenDay) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لا يمكن فتح يوم، يوجد يوم مفتوح بالفعل']);

            return;
        }

        DailyPaymentDateTime::create([
            'date' => $this->selectedDate,
        ]);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تم فتح يوم '.$this->selectedDate]);
        $this->currentOpenDay = DailyPaymentDateTime::whereNull('end_date')->first();
    }

    public function closeDay(): void
    {
        abort_unless(auth()->user()->can('finance.edit'), 403);

        if (! $this->currentOpenDay) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لا يوجد يوم مفتوح لاغلاقه']);

            return;
        }

        $this->currentOpenDay->update([
            'end_date' => now(),
        ]);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تم غلق يوم '.$this->currentOpenDay->date]);
        $this->currentOpenDay = null;
    }

    public function clearRange(): void
    {
        $this->reset(['rangeFrom', 'rangeTo']);
    }

    /**
     * Resolves the active review window [label, start, end] or null when incomplete.
     */
    protected function activeWindow(): ?array
    {
        if ($this->reviewMode === 'day') {
            $day = DailyPaymentDateTime::whereDate('date', $this->selectedDate)->first();

            if (! $day) {
                return null;
            }

            return [$day->date, Carbon::parse($day->start_date), $day->end_date ? Carbon::parse($day->end_date) : now()];
        }

        if ($this->rangeFrom === '' || $this->rangeTo === '') {
            return null;
        }

        try {
            $from = Carbon::parse($this->rangeFrom);
            $to = Carbon::parse($this->rangeTo);
        } catch (\Exception $e) {
            return null;
        }

        if ($to->lt($from)) {
            return null;
        }

        return ['من '.$from->format('Y-m-d H:i').' إلى '.$to->format('Y-m-d H:i'), $from, $to];
    }

    protected function paidTickets(Carbon $start, Carbon $end): Collection
    {
        return StudentFeeTicket::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->with(['student.level', 'student.section'])
            ->orderBy('paid_at')
            ->get();
    }

    protected function movements(Carbon $start, Carbon $end): Collection
    {
        return WalletTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->with(['student', 'performedBy'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array{by_method: array<string, array{count: int, total: float}>, by_fee_type: array<string, array{count: int, total: float}>, count: int, gross_total: float, net_total: float, discount_total: float, full_discount_count: int, ministerial_count: int, ministerial_first: ?string, ministerial_last: ?string}
     */
    protected function buildBreakdown(Collection $tickets): array
    {
        $byMethod = [];
        foreach (array_keys(self::PAYMENT_METHODS) as $method) {
            $byMethod[$method] = ['count' => 0, 'total' => 0.0];
        }

        $byFeeType = [];
        foreach (array_keys(WalletsReport::FEE_TYPES) as $type) {
            $byFeeType[$type] = ['count' => 0, 'total' => 0.0];
        }

        foreach ($tickets as $ticket) {
            $method = $ticket->payment_method ?: 'cash';
            $byMethod[$method]['count'] += 1;
            $byMethod[$method]['total'] += (float) $ticket->amount;

            $type = array_key_exists($ticket->fee_type, WalletsReport::FEE_TYPES) ? $ticket->fee_type : 'other';
            $byFeeType[$type]['count'] += 1;
            $byFeeType[$type]['total'] += (float) $ticket->amount;
        }

        $receipts = $tickets->pluck('ministerial_receipt_number')->filter()->values()->sort();

        return [
            'by_method' => $byMethod,
            'by_fee_type' => $byFeeType,
            'count' => $tickets->count(),
            'gross_total' => (float) $tickets->sum(fn ($t) => $t->grossAmount()),
            'net_total' => (float) $tickets->sum('amount'),
            'discount_total' => (float) $tickets->sum('discount_amount'),
            'full_discount_count' => $tickets->filter(fn ($t) => (float) $t->amount <= 0 && (float) $t->grossAmount() > 0)->count(),
            'ministerial_count' => $receipts->count(),
            'ministerial_first' => $receipts->first(),
            'ministerial_last' => $receipts->last(),
        ];
    }

    public function exportCsv(): ?StreamedResponse
    {
        abort_unless(auth()->user()->can('finance.view'), 403);

        $window = $this->activeWindow();

        if (! $window) {
            $this->dispatch('toast', message: 'حدد يوماً أو فترة زمنية صحيحة أولاً.', type: 'error');

            return null;
        }

        $tickets = $this->paidTickets($window[1], $window[2]);
        $cashiers = $this->cashierMap($tickets);

        $filename = 'daily-payments-'.str_replace([' ', ':'], '-', $window[0]).'.csv';

        return response()->streamDownload(function () use ($tickets, $cashiers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'رقم الحافظة', 'اسم الطالب', 'كود الطالب', 'الفرقة', 'الشعبة', 'بيان المصروف', 'النوع',
                'المبلغ الأصلي', 'الخصم', 'المبلغ المسدد', 'طريقة الدفع', 'آخر 4 فيزا',
                'الإيصال الوزاري', 'موعد السداد', 'أمين الصندوق',
            ]);

            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->ticket_number,
                    $ticket->student?->name ?? '—',
                    $ticket->student?->username ?? '—',
                    $ticket->student?->level?->name ?? '—',
                    $ticket->student?->section?->name ?? '—',
                    $ticket->fee_name ?: (WalletsReport::FEE_TYPES[$ticket->fee_type] ?? $ticket->fee_type),
                    WalletsReport::FEE_TYPES[$ticket->fee_type] ?? $ticket->fee_type,
                    number_format($ticket->grossAmount(), 2, '.', ''),
                    number_format((float) $ticket->discount_amount, 2, '.', ''),
                    number_format((float) $ticket->amount, 2, '.', ''),
                    self::PAYMENT_METHODS[$ticket->payment_method] ?? 'غير محدد',
                    $ticket->visa_last_four ?? '',
                    $ticket->ministerial_receipt_number ?? '',
                    $ticket->paid_at?->format('Y-m-d H:i') ?? '',
                    $cashiers->get($ticket->id, '—'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return Collection<int, string> ticket_id => cashier name
     */
    protected function cashierMap(Collection $tickets): Collection
    {
        return WalletTransaction::query()
            ->where('type', WalletTransactionType::DEPOSIT->value)
            ->where('reference_type', (new StudentFeeTicket)->getMorphClass())
            ->whereIn('reference_id', $tickets->pluck('id'))
            ->get(['reference_id', 'performed_by_type', 'performed_by_id'])
            ->mapWithKeys(fn (WalletTransaction $m) => [(int) $m->reference_id => $m->performedBy?->name ?? '—']);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('finance.view'), 403);

        $days = DailyPaymentDateTime::orderBy('date', 'desc')->get();
        $window = $this->activeWindow();

        $tickets = collect();
        $movements = collect();
        $breakdown = null;
        $cashiers = collect();

        if ($window) {
            $tickets = $this->paidTickets($window[1], $window[2]);
            $movements = $this->movements($window[1], $window[2]);
            $breakdown = $this->buildBreakdown($tickets);
            $cashiers = $this->cashierMap($tickets);
        }

        $movementTotals = [
            'deposit' => ['count' => $movements->where('type', WalletTransactionType::DEPOSIT)->count(), 'total' => (float) $movements->where('type', WalletTransactionType::DEPOSIT)->sum('amount')],
            'withdrawal' => ['count' => $movements->where('type', WalletTransactionType::WITHDRAWAL)->count(), 'total' => (float) $movements->where('type', WalletTransactionType::WITHDRAWAL)->sum('amount')],
            'refund' => ['count' => $movements->where('type', WalletTransactionType::REFUND)->count(), 'total' => (float) $movements->where('type', WalletTransactionType::REFUND)->sum('amount')],
        ];

        $cashiersOnDuty = $movements->pluck('performedBy')->filter()->unique('id')->pluck('name')->values();

        return view('livewire.admin.finance.daily-payments', [
            'days' => $days,
            'window' => $window,
            'tickets' => $tickets,
            'movements' => $movements,
            'breakdown' => $breakdown,
            'movementTotals' => $movementTotals,
            'cashiers' => $cashiers,
            'cashiersOnDuty' => $cashiersOnDuty,
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
