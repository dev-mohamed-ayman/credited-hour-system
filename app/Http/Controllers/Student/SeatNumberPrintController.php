<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SeatNumberPrintController extends Controller
{
    /**
     * The student's own printable seat-number card, honouring the admin's seat card
     * display settings and mirroring the bulk print produced by student affairs.
     */
    public function __invoke(): View|RedirectResponse
    {
        $student = auth('student')->user()->load(['section.department', 'level', 'year']);

        if (blank($student->seat_number)) {
            return redirect()->route('student.dashboard')
                ->with('error', 'لم يتم تخصيص رقم جلوس لك بعد — تابع الشؤون الطلابية.');
        }

        $settings = Setting::query()->firstOrCreate([]);

        return view('student.pages.print_seat_number', compact('student', 'settings'));
    }
}
