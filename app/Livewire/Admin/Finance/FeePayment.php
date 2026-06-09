<?php

namespace App\Livewire\Admin\Finance;

use App\Models\Setting;
use App\Models\StudentFeeTicket;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FeePayment extends Component
{
    public $ticketNumber;

    public $ticket;

    public $ministerialReceiptNumber;

    public $paymentMethod = 'cash'; // 'cash', 'credit', 'both'

    public $visaLastFour;

    public $showForm = false;

    public function mount()
    {
        $this->generateNextReceiptNumber();
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

        $this->ticket = StudentFeeTicket::where('ticket_number', $this->ticketNumber)
            ->with(['student'])
            ->first();

        if (! $this->ticket) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لم يتم العثور على حافظة بهذا الرقم']);
            $this->showForm = false;

            return;
        }

        if ($this->ticket->status === 'paid') {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'هذه الحافظة مدفوعة بالفعل']);
            $this->showForm = false;

            return;
        }

        $this->showForm = true;
    }

    public function confirmPayment()
    {
        $this->validate([
            'paymentMethod' => 'required|in:cash,credit,both',
            'visaLastFour' => $this->paymentMethod !== 'cash' ? 'required|digits:4' : 'nullable',
        ]);

        $settings = Setting::first();
        $next = $settings->ministerial_receipt_current + 1;

        if ($next > $settings->ministerial_receipt_end) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لا يمكن السداد، لقد وصلت لنهاية مدى الأرقام الوزارية']);

            return;
        }

        DB::transaction(function () use ($settings, $next) {
            $this->ticket->update([
                'status' => 'paid',
                'ministerial_receipt_number' => $next,
                'payment_method' => $this->paymentMethod,
                'visa_last_four' => $this->visaLastFour,
                'paid_at' => now(),
            ]);

            $settings->update([
                'ministerial_receipt_current' => $next,
            ]);
        });

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تم سداد الحافظة بنجاح برقم إيصال وزاري: '.$next]);
        $this->reset(['ticketNumber', 'ticket', 'showForm', 'visaLastFour', 'paymentMethod']);
        $this->generateNextReceiptNumber();
    }

    public function render()
    {
        return view('livewire.admin.finance.fee-payment')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
