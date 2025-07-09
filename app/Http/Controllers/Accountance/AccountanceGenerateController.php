<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AccountanceGenerateController extends Controller
{
    public function generateAccountingPdf(Request $request)
    {
        // Validate date range input
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Get fully paid bookings within date range with nested relationships
        $bookings = Booking::where('payment_status', 'fully_paid')
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->with([
                'customer',
                'cashImages',
                'items.group.cashImages', // Simplified eager loading
                'items.group.customerDocuments' => function($query) {
                    $query->where('type', 'booking_confirm_letter');
                }
            ])
            ->orderBy('created_at')
            ->get();

        // Prepare data for PDF
        $pdfData = [];

        foreach ($bookings as $booking) {
            $bookingEntry = [
                'booking' => $booking,
                'documents' => []
            ];

            // 1. Add customer payment slips (booking->cashImage)
            if ($booking->relationLoaded('cashImages')) {
                foreach ($booking->cashImages as $image) {
                    if (isset($image->image) && Storage::exists('images/' . $image->image)) {
                        $bookingEntry['documents'][] = [
                            'type' => 'customer_payment_slip',
                            'path' => Storage::path('images/' . $image->image)
                        ];
                    }
                }
            }

            // 2. Add Thai company expense receipts and hotel invoices from groups
            if ($booking->relationLoaded('items')) {
                foreach ($booking->items as $item) {
                    if ($item->relationLoaded('group') && $item->group) {
                        // Add group cash images (expense receipts)
                        if ($item->group->relationLoaded('cashImages')) {
                            foreach ($item->group->cashImages as $image) {
                                if (isset($image->image) && Storage::exists('images/' . $image->image)) {
                                    $bookingEntry['documents'][] = [
                                        'type' => 'expense_receipt',
                                        'path' => Storage::path('images/' . $image->image)
                                    ];
                                }
                            }
                        }

                        // Add hotel/attraction invoices (booking_confirm_letter)
                        if ($item->group->relationLoaded('customerDocuments')) {
                            foreach ($item->group->customerDocuments as $document) {
                                if (isset($document->image) && Storage::exists('images/' . $document->image)) {
                                    $bookingEntry['documents'][] = [
                                        'type' => 'booking_confirm_letter',
                                        'path' => Storage::path('images/' . $document->image)
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            // 3. Add Myanmar company invoice (generated PDF)
            $booking->sub_total_with_vat = $booking->grand_total - $booking->commission;
            $booking->vat = ($booking->sub_total_with_vat - $booking->commission) * 0.07;
            $booking->total_excluding_vat = $booking->sub_total_with_vat - $booking->vat;

            $bookingEntry['documents'][] = [
                'type' => 'myanmar_invoice',
                'booking' => $booking
            ];

            $pdfData[] = $bookingEntry;
        }

        // return response()->json($pdfData);

        // Generate PDF
        $pdf = Pdf::setOption([
            'fontDir' => public_path('/fonts'),
            'isRemoteEnabled' => true
        ])->loadView('pdf.accounting_report', ['data' => $pdfData]);

        return $pdf->stream('accounting_report_' . $request->start_date . '_to_' . $request->end_date . '.pdf');
    }
}
