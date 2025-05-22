<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingItemGroup;
use App\Models\CustomerDocument;
use App\Traits\HttpResponses;
use Exception;

class CustomerDocumentController extends Controller
{
    use HttpResponses;

    public function delete(BookingItemGroup $booking_item_group, CustomerDocument $customer_document)
    {
        try {
            delete_file($customer_document->file, 'customer_documents');

            $customer_document->delete();

            return $this->success(null, 'Customer Document deleted successfully');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
