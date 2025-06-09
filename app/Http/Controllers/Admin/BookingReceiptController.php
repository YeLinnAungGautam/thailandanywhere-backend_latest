<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingReceiptResource;
use App\Models\BookingReceipt;
use App\Models\ReservationExpenseReceipt;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookingReceiptController extends Controller
{
    use ImageManager;
    use HttpResponses;

    public function index(string $booking_id)
    {
        try {
            $passports = BookingReceipt::where('booking_id', $booking_id)->get();

            return $this->success(BookingReceiptResource::collection($passports)
                ->additional([
                    'meta' => [
                        'total_page' => (int)ceil($passports->total() / $passports->perPage()),
                    ],
                ])
                ->response()
                ->getData(), 'File List');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }



    public function store(string $booking_id, Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'amount' => 'nullable',
            'bank_name' => 'nullable',
            'date' => 'nullable',
            'is_corporate' => 'nullable',
            'note' => 'nullable',
            'sender' => 'nullable',
        ]);

        try {
            $fileData = $this->uploads($request->file, 'images/');

            $passport = BookingReceipt::create([
                'booking_id' => $booking_id,
                'image' => $fileData['fileName'],
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'date' => $request->date,
                'is_corporate' => $request->is_corporate,
                'note' => $request->note,
                'sender' => $request->sender,
            ]);

            return $this->success(new BookingReceiptResource($passport), 'File Created');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function update(string $booking_id, string $id, Request $request)
    {
        $request->validate([
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'amount' => 'nullable',
            'bank_name' => 'nullable',
            'date' => 'nullable',
            'is_corporate' => 'nullable',
            'note' => 'nullable',
            'sender' => 'nullable',
        ]);

        try {
            $passport = BookingReceipt::find($id);

            if (!$passport) {
                return $this->error(null, 'File not found');
            }

            if ($request->hasFile('file')) {
                $fileData = $this->uploads($request->file, 'images/');
            }

            $passport->update([
                'image' => $fileData['fileName'] ?? $passport->image,
                'amount' => $request->amount ?? $passport->amount,
                'bank_name' => $request->bank_name ?? $passport->bank_name,
                'date' => $request->date ?? $passport->date,
                'is_corporate' => $request->is_corporate ?? $passport->is_corporate,
                'note' => $request->note ?? $passport->note,
                'sender' => $request->sender ?? $passport->sender,
            ]);

            return $this->success(new BookingReceiptResource($passport), 'File Updated');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $booking_id, string $id)
    {
        try {
            $passport = BookingReceipt::find($id);

            if (!$passport) {
                return $this->error(null, 'File not found');
            }

            $passport->delete();

            return $this->success(null, 'File Deleted');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function getall(Request $request)
    {
        try {
            $PER_PAGE = 10;
            $filters = $this->extractFilters($request);

            // Get filtered data from both tables
            $reservationReceipts = $this->getReservationReceipts($filters);
            $bookingReceipts = $this->getBookingReceipts($filters);

            // Combine and paginate results
            $paginatedData = $this->paginateResults($reservationReceipts, $bookingReceipts, $request, $PER_PAGE);

            return $this->success([
                'data' => $paginatedData['data'],
                'meta' => $this->buildMetaData($paginatedData),
                'summary' => [
                    'reservation_expense_receipts' => $reservationReceipts->count(),
                    'booking_receipts' => $bookingReceipts->count(),
                    'total_records' => $paginatedData['total']
                ]
            ], 'All receipts retrieved successfully');

        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    private function buildMetaData($paginatedData)
    {
        $currentPage = $paginatedData['current_page'];
        $lastPage = $paginatedData['last_page'];
        $path = $paginatedData['path'];

        $links = [];

        // Previous link
        $links[] = [
            'url' => $currentPage > 1 ? $paginatedData['prev_page_url'] : null,
            'label' => '&laquo; Previous',
            'active' => false
        ];

        // First page
        $links[] = [
            'url' => $path . '?page=1',
            'label' => '1',
            'active' => $currentPage == 1
        ];

        // Calculate window of pages around current page
        $start = max(2, $currentPage - 4);
        $end = min($lastPage - 1, $currentPage + 4);

        // Add pages before current if needed
        if ($start > 2) {
            $links[] = [
                'url' => $path . '?page=' . ($start - 1),
                'label' => ($start - 1),
                'active' => false
            ];
        }

        // Add pages in window
        for ($i = $start; $i <= $end; $i++) {
            $links[] = [
                'url' => $path . '?page=' . $i,
                'label' => (string)$i,
                'active' => $i == $currentPage
            ];
        }

        // Add pages after current if needed
        if ($end < $lastPage - 1) {
            $links[] = [
                'url' => $path . '?page=' . ($end + 1),
                'label' => ($end + 1),
                'active' => false
            ];
        }

        // Last page
        if ($lastPage > 1) {
            $links[] = [
                'url' => $path . '?page=' . $lastPage,
                'label' => (string)$lastPage,
                'active' => $currentPage == $lastPage
            ];
        }

        // Next link
        $links[] = [
            'url' => $currentPage < $lastPage ? $paginatedData['next_page_url'] : null,
            'label' => 'Next &raquo;',
            'active' => false
        ];

        return [
            'current_page' => $currentPage,
            'from' => $paginatedData['from'],
            'last_page' => $lastPage,
            'links' => $links,
            'path' => $path,
            'per_page' => $paginatedData['per_page'],
            'to' => $paginatedData['to'],
            'total' => $paginatedData['total'],
            'total_page' => $lastPage,
        ];
    }

    private function extractFilters(Request $request)
    {
        return [
            'type' => $request->type,
            'sender' => $request->sender,
            'amount' => $request->amount,
            'date' => $request->date,
            'bank_name' => $request->bank_name
        ];
    }

    private function getReservationReceipts($filters)
    {
        $query = ReservationExpenseReceipt::query();

        $this->applyCommonFilters($query, $filters, false); // false = exclude sender

        return $query->get()->map(function($item) {
            return $this->formatReceipt($item, 'ReservationExpenseReceipt');
        });
    }

    private function getBookingReceipts($filters)
    {
        $query = BookingReceipt::query();

        $this->applyCommonFilters($query, $filters, true); // true = include sender

        return $query->get()->map(function($item) {
            return $this->formatReceipt($item, 'BookingReceipt');
        });
    }

    private function formatReceipt($item, $source)
    {
        // Determine the image/file field name based on the source
        $imageField = ($source === 'BookingReceipt') ? 'image' : 'file';

        return [
            'id' => $item->id,
            'table_source' => $source,
            'sender' => $item->sender ?? null,
            'amount' => $item->amount,
            'bank_name' => $item->bank_name,
            'date' => $item->date,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'receipt_url' => $item->{$imageField} ? Storage::url($source === 'BookingReceipt' ? 'images/' . $item->image : 'files/' . $item->file) : null,
            'receipt_type' => $source === 'BookingReceipt' ? 'customer_payment' : 'expense',
            'original_fields' => $item->toArray() // Include all original fields if needed
        ];
    }

    private function applyCommonFilters($query, $filters, $includeSender = false)
    {
        $this->applyTypeFilter($query, $filters['type'], $includeSender);
        $this->applySearchFilters($query, $filters, $includeSender);

        return $query;
    }

    private function applyTypeFilter($query, $type, $includeSender)
    {
        $requiredFields = ['amount', 'bank_name', 'date', 'created_at'];

        if ($includeSender) {
            $requiredFields[] = 'sender';
        }

        if ($type === 'complete') {
            foreach ($requiredFields as $field) {
                $query->whereNotNull($field)
                      ->where($field, '!=', '')
                      ->where($field, '!=', 'null');
            }
        } elseif (in_array($type, ['missing', 'incomplete'])) {
            $query->where(function($subQuery) use ($requiredFields) {
                foreach ($requiredFields as $field) {
                    $subQuery->orWhereNull($field)
                            ->orWhere($field, '')
                            ->orWhere($field, 'null');
                }
            });
        }
    }

    private function applySearchFilters($query, $filters, $includeSender)
    {
        // Sender filter (only for BookingReceipt)
        if ($includeSender && $filters['sender']) {
            $query->where('sender', 'like', '%' . $filters['sender'] . '%');
        }

        // Amount filter
        if ($filters['amount']) {
            $query->where('amount', $filters['amount']);
        }

        // Bank name filter
        if ($filters['bank_name']) {
            $query->where('bank_name', 'like', '%' . $filters['bank_name'] . '%');
        }

        // Date filter
        if ($filters['date']) {
            $this->applyDateFilter($query, $filters['date']);
        }
    }

    private function applyDateFilter($query, $date)
    {
        $dateArray = explode(',', $date);

        if (count($dateArray) === 2) {
            // Date range
            $startDate = date('Y-m-d 00:00:00', strtotime(trim($dateArray[0])));
            $endDate = date('Y-m-d 23:59:59', strtotime(trim($dateArray[1])));
            $query->whereBetween('date', [$startDate, $endDate]);
        } else {
            // Single date
            $singleDate = date('Y-m-d', strtotime($date));
            $query->whereDate('date', $singleDate);
        }
    }

    private function paginateResults($reservationReceipts, $bookingReceipts, $request, $perPage)
    {
        // Combine and sort
        $allReceipts = $reservationReceipts->merge($bookingReceipts)
                                         ->sortByDesc('created_at')
                                         ->values();

        // Pagination calculations
        $currentPage = (int) $request->get('page', 1);
        $total = $allReceipts->count();
        $offset = ($currentPage - 1) * $perPage;
        $lastPage = ceil($total / $perPage);

        // Get paginated items
        $paginatedItems = $allReceipts->slice($offset, $perPage)->values();

        // Build pagination URLs
        $baseUrl = request()->url();
        $queryParams = $request->except('page');
        $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

        return [
            'current_page' => $currentPage,
            'data' => $paginatedItems,
            'first_page_url' => $baseUrl . '?page=1' . $queryString,
            'from' => $total > 0 ? $offset + 1 : null,
            'last_page' => $lastPage,
            'last_page_url' => $baseUrl . '?page=' . $lastPage . $queryString,
            'next_page_url' => $currentPage < $lastPage ? $baseUrl . '?page=' . ($currentPage + 1) . $queryString : null,
            'path' => $baseUrl,
            'per_page' => $perPage,
            'prev_page_url' => $currentPage > 1 ? $baseUrl . '?page=' . ($currentPage - 1) . $queryString : null,
            'to' => min($offset + $perPage, $total),
            'total' => $total
        ];
    }
}
