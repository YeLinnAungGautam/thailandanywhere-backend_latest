<?php
namespace App\Http\Controllers;

use App\Exports\AirlineExport;
use App\Imports\AirlineImport;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;

class AirlineExportImportController extends Controller
{
    use HttpResponses;

    public function export()
    {
        try {
            $file_name = "airline_export_" . date('Y-m-d-H-i-s') . ".csv";

            \Excel::store(new AirlineExport(), "export/" . $file_name);

            return $this->success(['download_link' => get_file_link('export', $file_name)], 'success export', 200);
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file' => 'required|mimes:csv,txt']);

            \Excel::import(new AirlineImport, $request->file('file'));

            return $this->success(null, 'CSV import is successful');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
