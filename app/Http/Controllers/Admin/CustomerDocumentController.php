<?php
namespace App\Http\Controllers\Admin\GroupItem;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
use App\Models\BookingItemGroup;
use App\Models\CustomerDocument;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerDocumentController extends Controller
{
    use HttpResponses;

    protected $document_type_validation_rule = 'required|in:passport,tax_slip,paid_slip,receipt_image,booking_confirm_letter,confirmation_letter,car_photo,expense_receipt';

    public function index(BookingItemGroup $booking_item_group, Request $request)
    {
        $request->validate([
            'document_type' => $this->document_type_validation_rule,
        ]);

        $documents = $booking_item_group->customerDocuments()
            ->where('type', $request->document_type)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(CustomerDocumentResource::collection($documents), 'Document List');
    }

    public function store(BookingItemGroup $booking_item_group, Request $request)
    {
        $request->validate([
            'document_type' => $this->document_type_validation_rule,
            'documents' => 'required|array',
            'documents.*.file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'documents.*.meta' => 'nullable|array',
        ]);

        try {
            foreach ($request->documents as $document) {
                if (isset($document['file']) && $document['file']) {
                    $document_file = upload_file($document['file'], CustomerDocument::specificFolderPath($request->document_type));
                } else {
                    $document_file = [
                        'file' => null,
                        'filePath' => null,
                        'fileType' => null,
                        'fileSize' => null,
                    ];
                }

                $booking_item_group->customerDocuments()->create([
                    'type' => $request->document_type,
                    'file' => $document_file['file'] ?? null,
                    'file_name' => $document_file['filePath'] ?? null,
                    'mime_type' => $document_file['fileType'] ?? null,
                    'file_size' => $document_file['fileSize'] ?? null,
                    'meta' => $document['meta'] ?? null,
                ]);
            }

            return $this->success(null, 'Passport uploaded successfully');
        } catch (Exception $e) {
            Log::error('Error uploading customer document: ' . $e->getMessage(), [
                'booking_item_group_id' => $booking_item_group->id,
                'request' => $request->all(),
            ]);

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function update(BookingItemGroup $booking_item_group, CustomerDocument $customer_document, Request $request)
    {
        $request->validate([
            'document_type' => $this->document_type_validation_rule,
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'meta' => 'nullable|array',
        ]);

        try {
            $document = $booking_item_group->customerDocuments()->find($customer_document->id);

            if (!$document) {
                return $this->error(null, 'Customer document not found', 404);
            }

            if ($request->hasFile('file')) {
                if ($document->file) {
                    delete_file(CustomerDocument::specificFolderPath($document->type), $document->file);
                }

                $document = upload_file($request->file, CustomerDocument::specificFolderPath($request->document_type));
            }

            $document->update([
                'type' => $request->document_type,
                'file' => $document['file'] ?? $document->file,
                'file_name' => $document['filePath'] ?? $document->file_name,
                'mime_type' => $document['fileType'] ?? $document->mime_type,
                'file_size' => $document['fileSize'] ?? $document->file_size,
                'meta' => $request->meta ?? $document->meta,
            ]);

        } catch (Exception $e) {
            Log::error('Error updating customer document: ' . $e->getMessage(), [
                'booking_item_group_id' => $booking_item_group->id,
                'request' => $request->all(),
            ]);

            return $this->error($document->file_name, $e->getMessage(), 500);
        }
    }

    public function delete(BookingItemGroup $booking_item_group, CustomerDocument $customer_document)
    {
        try {
            $document = $booking_item_group->customerDocuments()->find($customer_document->id);

            if (!$document) {
                return $this->error(null, 'Customer document not found', 404);
            }

            delete_file(CustomerDocument::specificFolderPath($document->type), $document->file);

            $document->delete();

            return $this->success(null, 'Customer document deleted successfully');
        } catch (Exception $e) {
            Log::error('Error updating customer document: ' . $e->getMessage(), [
                'booking_item_group_id' => $booking_item_group->id,
                'customer_document_id' => $customer_document->id,
            ]);

            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
