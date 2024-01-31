<?php

namespace App\Http\Controllers;

use App\Exports\ReservationReportExport;
use Excel;
use Illuminate\Http\Request;

class ReservationExportController extends Controller
{
    public function exportReservationReport(Request $request)
    {
        return Excel::download(new ReservationReportExport($request->sale_daterange), "reservation_report_" . date('Y-m-d-H-i-s') . ".csv");
    }
}
