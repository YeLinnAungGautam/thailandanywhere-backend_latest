<?php

namespace App\Http\Controllers;

use App\Exports\ReservationReportExport;
use Excel;

class ReservationExportController extends Controller
{
    public function exportReservationReport()
    {
        return (new ReservationReportExport)->download('invoices.csv', Excel::CSV, ['Content-Type' => 'text/csv']);
    }
}
