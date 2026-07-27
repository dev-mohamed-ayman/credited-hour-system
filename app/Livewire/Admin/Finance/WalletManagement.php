<?php

namespace App\Livewire\Admin\Finance;

use App\Models\Student;
use App\Services\WalletService;
use Livewire\Component;

class WalletManagement extends Component
{
    public $searchQuery;
    public $student;

    public function searchStudent()
    {
        $this->validate([
            'searchQuery' => 'required|string',
        ]);

        $this->student = Student::where('username', 'like', '%' . $this->searchQuery . '%')
            ->orWhere('name', 'like', '%' . $this->searchQuery . '%')
            ->with(['wallet', 'walletTransactions' => fn ($q) => $q->with('year')->latest()])
            ->first();

        if (!$this->student) {
            $this->dispatch('toast', message: 'لم يتم العثور على طالب بهذا الاسم أو الكود', type: 'error');
            return;
        }
    }

    public function selectStudent($studentId)
    {
        $this->student = Student::with(['wallet', 'walletTransactions' => fn ($q) => $q->with('year')->latest()])
            ->find($studentId);

        if (!$this->student) {
            $this->dispatch('toast', message: 'لم يتم العثور على الطالب', type: 'error');
            return;
        }
    }

    public function clearSearch()
    {
        $this->searchQuery = '';
        $this->student = null;
    }

    public function getWalletBalanceProperty(): float
    {
        if (!$this->student) {
            return 0;
        }

        return app(WalletService::class)->getBalance($this->student);
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

        return view('livewire.admin.finance.wallet-management')
            ->with('recentStudents', $recentStudents)
            ->with('walletBalance', $this->walletBalance)
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
