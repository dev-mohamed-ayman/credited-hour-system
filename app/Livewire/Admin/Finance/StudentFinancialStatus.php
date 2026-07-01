<?php

namespace App\Livewire\Admin\Finance;

use App\Models\Student;
use App\Models\Year;
use Illuminate\Support\Collection;
use Livewire\Component;

class StudentFinancialStatus extends Component
{
    public $searchQuery;
    public $student;
    public $selectedStudentId;

    public function searchStudent()
    {
        $this->validate([
            'searchQuery' => 'required|string',
        ]);

        $this->student = Student::where('username', 'like', '%' . $this->searchQuery . '%')
            ->orWhere('name', 'like', '%' . $this->searchQuery . '%')
            ->with(['feeTickets' => fn ($q) => $q->orderByDesc('created_at')->with(['year', 'department', 'level', 'section'])])
            ->first();

        if (!$this->student) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'لم يتم العثور على طالب بهذا الاسم أو الكود'
            ]);
            return;
        }

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'تم العثور على الطالب بنجاح'
        ]);
    }

    public function selectStudent($studentId)
    {
        $this->student = Student::with(['feeTickets' => fn ($q) => $q->orderByDesc('created_at')->with(['year', 'department', 'level', 'section'])])
            ->find($studentId);

        if (!$this->student) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'لم يتم العثور على الطالب'
            ]);
            return;
        }
    }

    public function clearSearch()
    {
        $this->searchQuery = '';
        $this->student = null;
    }

    public function getGroupedTicketsProperty(): Collection
    {
        if (!$this->student) {
            return collect();
        }

        return $this->student->feeTickets
            ->groupBy(function ($ticket) {
                return $ticket->year?->year . ' - ' . $ticket->semester?->label();
            })
            ->sortKeysDesc();
    }

    public function getTotalPaidProperty(): float
    {
        if (!$this->student) {
            return 0;
        }

        return $this->student->feeTickets->sum('paid');
    }

    public function getTotalPendingProperty(): float
    {
        return $this->remaining;
    }

    public function getRemainingProperty(): float
    {
        return $this->totalFees - $this->totalPaid;
    }

    public function getTotalFeesProperty(): float
    {
        if (!$this->student) {
            return 0;
        }

        return $this->student->feeTickets->sum('amount');
    }

    public function render()
    {
        $recentStudents = collect();
        if (empty($this->student) && strlen($this->searchQuery) >= 2) {
            $recentStudents = Student::where('username', 'like', '%' . $this->searchQuery . '%')
                ->orWhere('name', 'like', '%' . $this->searchQuery . '%')
                ->limit(10)
                ->get();
        }

        return view('livewire.admin.finance.student-financial-status')
            ->with('recentStudents', $recentStudents)
            ->with('totalFees', $this->totalFees)
            ->with('totalPaid', $this->totalPaid)
            ->with('remaining', $this->remaining)
            ->with('totalPending', $this->totalPending)
            ->with('groupedTickets', $this->groupedTickets)
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
