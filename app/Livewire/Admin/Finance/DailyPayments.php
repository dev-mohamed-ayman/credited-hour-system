<?php

namespace App\Livewire\Admin\Finance;

use App\Models\DailyPaymentDateTime;
use App\Models\StudentFeeTicket;
use Carbon\Carbon;
use Livewire\Component;

class DailyPayments extends Component
{
    public $selectedDate;
    public $currentOpenDay;

    public function mount()
    {
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentOpenDay = DailyPaymentDateTime::whereNull('end_date')->first();
    }

    public function openDay()
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

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تم فتح يوم ' . $this->selectedDate]);
        $this->currentOpenDay = DailyPaymentDateTime::whereNull('end_date')->first();
    }

    public function closeDay()
    {
        abort_unless(auth()->user()->can('finance.edit'), 403);

        if (!$this->currentOpenDay) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لا يوجد يوم مفتوح لاغلاقه']);
            return;
        }

        $this->currentOpenDay->update([
            'end_date' => now(),
        ]);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تم غلق يوم ' . $this->currentOpenDay->date]);
        $this->currentOpenDay = null;
    }

    public function render()
    {
        $days = DailyPaymentDateTime::orderBy('date', 'desc')->get();
        
        $selectedDay = DailyPaymentDateTime::where('date', $this->selectedDate)->first();
        
        $tickets = collect();
        
        if ($selectedDay) {
            $query = StudentFeeTicket::where('status', 'paid')
                ->with('student');
            
            if ($selectedDay->end_date) {
                $query->whereBetween('paid_at', [$selectedDay->start_date, $selectedDay->end_date]);
            } else {
                $query->where('paid_at', '>=', $selectedDay->start_date);
            }
            
            $tickets = $query->get();
        }

        return view('livewire.admin.finance.daily-payments', [
            'days' => $days,
            'selectedDay' => $selectedDay,
            'tickets' => $tickets,
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
