<?php
namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItem\BookingItemGroupDetailResource;
use App\Http\Resources\BookingItem\BookingItemGroupListResource;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
use App\Models\BookingItemGroup;
use App\Models\CustomerDocument;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Exception;

class ReservationController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        try {
            $query = BookingItemGroup::query()
                ->has('bookingItems')
                ->with([
                    'booking',
                    'booking.customer',
                    'bookingItems',
                    'bookingItems.product'
                ]);

            // Product ID filter
            $this->applyProductFilter($query, $request);

            // CRM ID filter
            $this->applyCrmFilter($query, $request);

            // Date Range filter
            $this->applyDateRangeFilter($query, $request);

            $bookingItemGroups = $query->paginate($request->limit ?? 20);

            return $this->success(
                BookingItemGroupListResource::collection($bookingItemGroups)->additional([
                    'meta' => [
                        'total_page' => (int)ceil($bookingItemGroups->total() / $bookingItemGroups->perPage()),
                    ],
                ])
                ->response()
                ->getData(),
                'Booking Item Groups'
            );

        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    private function applyProductFilter($query, $request)
    {
        if (!$request->productIds && !$request->productId) {
            return;
        }

        // Handle productIds parameter (comma-separated string or array)
        $productIds = [];

        if ($request->productIds) {
            $productIds = is_array($request->productIds)
                ? $request->productIds
                : explode(',', $request->productIds);
        } elseif ($request->productId) {
            $productIds = is_array($request->productId)
                ? $request->productId
                : explode(',', $request->productId);
        }

        // Clean up the array
        $productIds = array_filter(array_map('trim', $productIds));

        if (!empty($productIds)) {
            $query->whereHas('bookingItems', function ($q) use ($productIds, $request) {
                $q->whereIn('product_id', $productIds);

                // Add product type filter if specified
                if ($request->productType) {
                    $q->where('product_type', $request->productType);
                }
            });
        }
    }

    private function applyCrmFilter($query, $request)
    {
        if ($request->crm_id) {
            $query->whereHas('booking', function ($q) use ($request) {
                $q->where('crm_id', $request->crm_id);
            });
        }
    }

    private function applyDateRangeFilter($query, $request)
    {
        if (!$request->dateRange) {
            return;
        }

        $dates = array_map('trim', explode(',', $request->dateRange));

        if (count($dates) === 2) {
            $query->whereHas('bookingItems', function ($q) use ($dates) {
                $q->whereBetween('service_date', $dates);
            });
        }
    }

    protected $document_type_validation_rule = 'required|in:invoice,expense_receipt,passport,booking_request_proof,expense_mail_proof,assign_driver,booking_confirm_letter,confirmation_letter,tax_receipt,tax_slip';

    public function getCustomerDocuments($id,Request $request)
    {
        $request->validate([
            'document_type' => $this->document_type_validation_rule,
        ]);

        $documents = BookingItemGroup::find($id)->customerDocuments()
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

            return $this->success(null, 'Passport updated successfully');
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
