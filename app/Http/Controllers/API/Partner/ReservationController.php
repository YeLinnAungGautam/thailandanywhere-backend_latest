<?php
namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItem\BookingItemGroupDetailResource;
use App\Http\Resources\BookingItem\BookingItemGroupListResource;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
use App\Http\Resources\BookingItemPartnerListResource;
use App\Models\BookingItem;
use App\Models\BookingItemGroup;
use App\Models\CustomerDocument;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        try {
            // Build the base query
            $query = BookingItemGroup::query()
                ->has('bookingItems')
                ->with([
                    'booking',
                    'booking.customer',
                    'bookingItems',
                    'bookingItems.product'
                ]);

            // Apply filters
            $this->applyFilters($query, $request);

            // Apply product filters
            $this->applyProductFilter($query, $request);

            // Apply sorting
            $this->applySorting($query, $request);

            // Paginate results
            $groups = $query->paginate($request->get('limit', 20));

            return $this->success(
                BookingItemGroupListResource::collection($groups)->additional([
                    'meta' => [
                        'total_page' => (int)ceil($groups->total() / $groups->perPage()),
                        'total_count' => $groups->total(),
                        'total_nights' => $this->getTotalNights($groups),
                    ],
                ])->response()->getData(),
                'Filtered Booking Groups'
            );

        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getBookingItems(Request $request)
    {
        try {
            $productId = $request->product_id;
            $productType = $request->product_type;
            $crm_id = $request->crm_id;
            $date_range = $request->date_range;

            $query = BookingItem::query()->with([
                'product','group.cashImages','room','booking','booking.customer'
            ]);
            $limit = $request->limit ?? 10;

            if ($productId) {
                $query->where('product_id', $productId);
            }

            if ($productType) {
                $query->where('product_type', $productType);
            }

            if ($crm_id) {
                $query->where('crm_id', $crm_id);
            }

            $query->where('payment_status', 'fully_paid');

            $query->whereHas('room', function ($q) {
                $q->where('is_extra', 0);
            });

            if ($date_range) {
                $dates = array_map('trim', explode(',', $date_range));
                if (count($dates) === 2) {
                    $query->whereBetween('service_date', $dates);
                } elseif (count($dates) === 1) {
                    $query->whereDate('service_date', $dates[0]);
                }
            }

            $query->whereNull('deleted_at');

            $bookingItems = $query->paginate($limit);

            return $this->success(
                BookingItemPartnerListResource::collection($bookingItems)->additional([
                    'meta' => [
                        'total_page' => (int)ceil($bookingItems->total() / $bookingItems->perPage()),

                    ],
                ])->response()->getData(),
                'Filtered Booking Items'
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

    private function applyFilters($query, $request)
    {
        // Date Range Filter
        if ($request->date_range) {
            $dates = array_map('trim', explode(',', $request->date_range));
            if (count($dates) === 2) {
                $query->whereHas('bookingItems', function ($q) use ($dates) {
                    $q->whereBetween('service_date', $dates);
                });
            } elseif (count($dates) === 1) {
                $query->whereHas('bookingItems', function ($q) use ($dates) {
                    $q->whereDate('service_date', $dates[0]);
                });
            }
        }

        if($request->is_allowment_have){
            $query->whereHas('bookingItems', function ($q) {
                $q->where('is_allowment_have', 1);
            });
        }

        // Invoice Filter (based on booking confirm letter documents)
        if ($request->invoice) {
            if ($request->invoice === 'received') {
                $query->whereHas('customerDocuments', function ($q) {
                    $q->where('type', 'booking_confirm_letter');
                });
            } elseif ($request->invoice === 'not_received') {
                $query->whereDoesntHave('customerDocuments', function ($q) {
                    $q->where('type', 'booking_confirm_letter');
                });
            }
        }

        // CRM ID Filter
        if ($request->crm_id) {
            $query->whereHas('booking', function ($q) use ($request) {
                $q->where('crm_id', $request->crm_id);
            });
        }

        // Customer Name Filter
        if ($request->customer_name) {
            $query->whereHas('booking.customer', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->customer_name . '%');
            });
        }

        // Expense Item Status Filter
        if ($request->expense_item_status == 'fully_paid') {
            $query->has('cashImages');
        }
    }

    private function applySorting($query, $request)
    {
        if ($request->sorting_by_date) {
            $direction = $request->sorting_direction === 'desc' ? 'desc' : 'asc';

            // Sort by earliest service date
            $query->joinSub(
                DB::table('booking_items')
                    ->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                    ->groupBy('group_id'),
                'earliest_service_dates',
                function($join) {
                    $join->on('booking_item_groups.id', '=', 'earliest_service_dates.group_id');
                }
            )->orderBy('earliest_service_dates.earliest_service_date', $direction);
        } else {
            // Default sorting by created_at
            $query->latest();
        }
    }

    // ညီစေရန် - Extra room filter ကို ReservationController မှာ ထည့်မယ်
    private function getTotalNights($groups)
    {
        return $groups->sum(function ($group) {
            return $group->bookingItems
                ->filter(function($item) {
                    // Extra room မဟုတ်တဲ့ items တွေပဲ တွက်မယ်
                    return !($item->room && $item->room->is_extra == 1);
                })
                ->sum(function ($item) {
                    if ($item->checkin_date && $item->checkout_date) {
                        return $item->quantity * Carbon::parse($item->checkout_date)
                            ->diffInDays(Carbon::parse($item->checkin_date));
                    }
                    return $item->quantity; // Date မရှိရင် quantity ပဲ return
                });
        });
    }

    public function detail($id, Request $request)
    {
        try {
            $booking_item_group = BookingItemGroup::with([
                'booking',
                'bookingItems',
                'bookingItems.product',
                'customerDocuments',
                'cashImages',
            ])->find($id);

            return $this->success(new BookingItemGroupDetailResource($booking_item_group), 'Booking Item Group Detail');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
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

    public function store($id, Request $request)
    {
        $request->validate([
            'document_type' => $this->document_type_validation_rule,
            'documents' => 'required|array',
            'documents.*.file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'documents.*.meta' => 'nullable|array',
        ]);

        $booking_item_group = BookingItemGroup::find($id);

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

    public function update($id, $customer_document_id, Request $request)
    {
        $request->validate([
            'document_type' => $this->document_type_validation_rule,
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'meta' => 'nullable|array',
        ]);

        try {
            $document = BookingItemGroup::find($id)->customerDocuments()->find($customer_document_id);

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
            Log::error('Error updating customer document: ' . $e->getMessage());

            return $this->error($document->file_name, $e->getMessage(), 500);
        }
    }

    public function delete($id, $customer_document_id, Request $request)
    {
        try {
            $document = BookingItemGroup::find($id)->customerDocuments()->find($customer_document_id);

            if (!$document) {
                return $this->error(null, 'Customer document not found', 404);
            }

            delete_file(CustomerDocument::specificFolderPath($document->type), $document->file);

            $document->delete();

            return $this->success(null, 'Customer document deleted successfully');
        } catch (Exception $e) {
            Log::error('Error updating customer document: ' . $e->getMessage());

            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
