<?php

namespace App\Livewire\Admin\Finance;

use App\Models\DailyPaymentDateTime;
use App\Models\Setting;
use App\Models\StudentFeeTicket;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FeePayment extends Component
{
    public $ticketNumber;

    public $tickets = [];

    public $ticket;

    public $selectedTickets = [];

    public $selectAll = true;

    public $ministerialReceiptNumber;

    public $paymentMethod = 'cash'; // 'cash', 'credit', 'both'

    public $visaLastFour;

    public $showForm = false;

    public $currentOpenDay;

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedTickets = collect($this->tickets)
                ->filter(fn ($t) => $t->status !== 'paid')
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedTickets = [];
        }
    }

    public function updatedSelectedTickets()
    {
        $unpaidTicketIds = collect($this->tickets)
            ->filter(fn ($t) => $t->status !== 'paid')
            ->pluck('id')
            ->toArray();

        $this->selectAll = empty(array_diff($unpaidTicketIds, $this->selectedTickets));
    }

    public function mount()
    {
        $this->generateNextReceiptNumber();
        $this->currentOpenDay = DailyPaymentDateTime::whereNull('end_date')->first();
    }

    public function generateNextReceiptNumber()
    {
        $settings = Setting::first();
        if ($settings) {
            $next = $settings->ministerial_receipt_current + 1;
            if ($next > $settings->ministerial_receipt_end) {
                $this->ministerialReceiptNumber = 'انتهى المدى المحدد';
            } else {
                $this->ministerialReceiptNumber = $next;
            }
        } else {
            $this->ministerialReceiptNumber = 'لم يتم ضبط الإعدادات';
        }
    }

    public function searchTicket()
    {
        $this->validate([
            'ticketNumber' => 'required|string',
        ]);

        // Parse the ticket number input
        $parts = explode(',', $this->ticketNumber);

        if (count($parts) === 1) {
            // Single ticket
            $ticket = StudentFeeTicket::where('ticket_number', $parts[0])
                ->with(['student', 'year'])
                ->first();

            if (! $ticket) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'لم يتم العثور على حافظة بهذا الرقم']);
                $this->showForm = false;
                $this->tickets = [];

                return;
            }

            $this->tickets = [$ticket];
        } else {
            // Range of tickets: first part is full number, second part is last seconds
            $firstTicketNumber = $parts[0];
            $lastSeconds = str_pad(trim($parts[1]), 2, '0', STR_PAD_LEFT);

            // Extract base ticket number parts
            $basePrefix = substr($firstTicketNumber, 0, 10); // up to and including minutes
            $firstSeconds = substr($firstTicketNumber, 10, 2);
            $studentCode = substr($firstTicketNumber, 12);

            // Find all tickets between first and last seconds
            $foundTickets = [];
            $startSec = (int) $firstSeconds;
            $endSec = (int) $lastSeconds;

            // Determine the range (handle wrap-around if needed)
            $range = [];
            if ($endSec >= $startSec) {
                $range = range($startSec, $endSec);
            } else {
                // Wrap around (though unlikely in our case since tickets are sequential)
                $range = array_merge(range($startSec, 59), range(0, $endSec));
            }

            foreach ($range as $sec) {
                $secStr = str_pad($sec, 2, '0', STR_PAD_LEFT);
                $ticketNum = $basePrefix.$secStr.$studentCode;
                $ticket = StudentFeeTicket::where('ticket_number', $ticketNum)
                    ->with(['student', 'year'])
                    ->first();

                if ($ticket) {
                    $foundTickets[] = $ticket;
                }
            }

            if (empty($foundTickets)) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'لم يتم العثور على حوافظ بهذه الأرقام']);
                $this->showForm = false;
                $this->tickets = [];

                return;
            }

            $this->tickets = $foundTickets;
        }

        // Check if any of the tickets are already paid
        $paidTickets = array_filter($this->tickets, fn ($t) => $t->status === 'paid');
        if (! empty($paidTickets)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'بعض الحوافظ مدفوعة بالفعل']);
        }

        // Select all unpaid tickets by default
        $this->selectedTickets = collect($this->tickets)
            ->filter(fn ($t) => $t->status !== 'paid')
            ->pluck('id')
            ->toArray();

        $this->ticket = $this->tickets[0] ?? null;
        $this->showForm = true;
    }

    public function confirmPayment()
    {
        abort_unless(auth()->user()->can('finance.edit'), 403);

        $this->currentOpenDay = DailyPaymentDateTime::whereNull('end_date')->first();

        if (! $this->currentOpenDay) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لا يمكن السداد، لا يوجد يوم مفتوح']);

            return;
        }

        $this->validate([
            'paymentMethod' => 'required|in:cash,credit,both',
            'visaLastFour' => $this->paymentMethod !== 'cash' ? 'required|digits:4' : 'nullable',
            'selectedTickets' => 'required|array|min:1',
        ], [
            'selectedTickets.min' => 'يجب تحديد حافظة واحدة على الأقل للسداد',
        ]);

        $selectedTicketModels = collect($this->tickets)
            ->filter(fn ($t) => in_array($t->id, $this->selectedTickets) && $t->status !== 'paid')
            ->values();

        $settings = Setting::first();
        $next = $settings->ministerial_receipt_current + 1;
        $registrationFeesCount = $selectedTicketModels->filter(fn ($t) => $t->fee_type === 'registration')->count();

        if ($registrationFeesCount > 0 && $next > $settings->ministerial_receipt_end) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لا يمكن السداد، لقد وصلت لنهاية مدى الأرقام الوزارية']);

            return;
        }

        DB::transaction(function () use ($settings, $next, $registrationFeesCount, $selectedTicketModels) {
            $currentReceiptNumber = $next;

            foreach ($selectedTicketModels as $ticket) {
                $updateData = [
                    'status' => 'paid',
                    'payment_method' => $this->paymentMethod,
                    'visa_last_four' => $this->visaLastFour,
                    'paid_at' => now(),
                ];

                if ($ticket->fee_type === 'registration') {
                    $updateData['ministerial_receipt_number'] = $currentReceiptNumber;
                    $currentReceiptNumber++;
                }

                $ticket->update($updateData);

                app(\App\Services\WalletService::class)->deposit(
                    student: $ticket->student,
                    amount: $ticket->amount,
                    yearId: $ticket->year_id,
                    semester: $ticket->semester,
                    reason: 'إيداع مبلغ مالي من سداد حافظة',
                    reference: $ticket,
                    performedBy: auth()->user()
                );
            }

            if ($registrationFeesCount > 0) {
                $settings->update([
                    'ministerial_receipt_current' => $currentReceiptNumber - 1,
                ]);
            }
        });

        $totalAmount = $selectedTicketModels->sum('amount');
        $message = 'تم سداد '.$selectedTicketModels->count().' حافظة بنجاح بمبلغ إجمالي: '.number_format($totalAmount, 2).' ج.م';

        $this->dispatch('alert', ['type' => 'success', 'message' => $message]);
        $this->reset(['ticketNumber', 'tickets', 'showForm', 'visaLastFour', 'paymentMethod', 'selectedTickets', 'selectAll']);
        $this->generateNextReceiptNumber();
    }

    public function render()
    {
        return view('livewire.admin.finance.fee-payment')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
