<?php

namespace App\Http\Controllers;

use App\Exports\ReservationReportExport;
use Illuminate\Http\Request;

class ReservationExportController extends Controller
{
    public function exportReservationReport(Request $request)
    {
        return new ReservationReportExport($request->sale_daterange);
    }
}
