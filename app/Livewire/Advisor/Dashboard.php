<?php

namespace App\Livewire\Advisor;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $advisor = auth('advisor')->user();
        $totalStudents = $advisor->students()->count();

        // Count how many of these students have registrations in the current active semester
        // Let's assume current active semester is fetched via Setting or Year.
        // For simplicity, we just show total students and maybe maximum capacity.
        
        $maxStudents = $advisor->max_students;

        return view('livewire.advisor.dashboard', [
            'advisor' => $advisor,
            'totalStudents' => $totalStudents,
            'maxStudents' => $maxStudents,
        ])
            ->extends('advisor.layouts.app')
            ->section('content');
    }
}
