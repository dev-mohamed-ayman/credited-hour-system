<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentFeeTicket;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function printTickets(Request $request)
    {
        $ticketNumbers = explode(',', $request->tickets);
        $tickets = StudentFeeTicket::with('student.level', 'student.section.department')
            ->whereIn('ticket_number', $ticketNumbers)
            ->get();

        if ($tickets->isEmpty()) {
            return redirect()->route('admin.finance.fee-issuance')->with('error', 'لم يتم العثور على الحوافظ المطلوبة');
        }

        $student = $tickets->first()->student;
        $totalAmount = $tickets->sum('amount');

        // Prepare data for the view
        $data = [
            'student' => $student,
            'tickets' => $tickets,
            'totalAmount' => $totalAmount,
            'ticketNumbers' => $request->tickets, // For barcode
            'date' => now()->format('Y-m-d'),
            'notes' => $tickets->first()->notes,
        ];

        return view('admin.pages.finance.print-tickets', $data);
    }
}
