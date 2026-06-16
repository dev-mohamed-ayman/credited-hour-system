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
            ->orderBy('ticket_number')
            ->get();

        if ($tickets->isEmpty()) {
            return redirect()->route('admin.finance.fee-issuance')->with('error', 'لم يتم العثور على الحوافظ المطلوبة');
        }

        $student = $tickets->first()->student;
        $totalAmount = $tickets->sum('amount');

        // Format ticket numbers for display and barcode
        $formattedTicketNumbers = [];
        if (count($ticketNumbers) > 1) {
            $firstTicket = $tickets->first();
            $lastTicket = $tickets->last();
            $formattedTicketNumbers[] = $firstTicket->ticket_number;

            // Get the seconds part from the LAST ticket (positions 10 and 11, 0-based index)
            $lastSeconds = substr($lastTicket->ticket_number, 10, 2);
            $formattedTicketNumbers[] = $lastSeconds;
        } else {
            $formattedTicketNumbers = $ticketNumbers;
        }
        $formattedTicketNumbersStr = implode(',', $formattedTicketNumbers);

        // Prepare data for the view
        $data = [
            'student' => $student,
            'tickets' => $tickets,
            'totalAmount' => $totalAmount,
            'ticketNumbers' => $formattedTicketNumbersStr, // For barcode and display
            'fullTicketNumbers' => $request->tickets, // Keep full numbers just in case
            'date' => now()->format('Y-m-d'),
            'notes' => $tickets->first()->notes,
        ];

        return view('admin.pages.finance.print-tickets', $data);
    }
}
