<?php
namespace App\Http\Controllers\Admin;

use App\Exports\ReservationExport;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReservationListExportController extends Controller
{
    public function export(Request $request)
    {
        $request->validate([
            'daterange' => 'required',
            'product' => 'required',
            'filter_type' => 'nullable'
        ]);

        try {
            $file_name = "reservation_export_" . date('Y-m-d-H-i-s') . ".xlsx";

            \Excel::store(new ReservationExport($request->daterange, $request->product, $request->filter_type), "export/" . $file_name);

            return success(['download_link' => get_file_link('export', $file_name)], 'success export', 200);
        } catch (Exception $e) {
            Log::error($e);

            return failedMessage($e->getMessage());
        }
    }
}
