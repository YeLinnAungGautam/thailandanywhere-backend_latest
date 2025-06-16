<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ReservationExpenseReceipt;
use App\Models\BookingReceipt;
use Exception;

class ReceiptService
{
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

            return [
                'success' => true,
                'data' => [
                    'data' => $paginatedData['data'],
                    'meta' => $this->buildMetaData($paginatedData),
                    'summary' => [
                        'reservation_expense_receipts' => $reservationReceipts->count(),
                        'booking_receipts' => $bookingReceipts->count(),
                        'total_records' => $paginatedData['total']
                    ]
                ],
                'message' => 'All receipts retrieved successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage()
            ];
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
            'bank_name' => $request->bank_name,
            'crm_id' => $request->crm_id // Add this line
        ];
    }

    private function getReservationReceipts($filters)
    {
        $query = ReservationExpenseReceipt::with(['reservation' => function($q) use ($filters) {
            if (!empty($filters['crm_id'])) {
                $q->where('crm_id', 'like', '%' . $filters['crm_id'] . '%');
            }
        }]);

        $this->applyCommonFilters($query, $filters, false); // false = exclude sender

        return $query->get()->map(function($item) {
            return $this->formatReceipt($item, 'ReservationExpenseReceipt');
        });
    }

    private function getBookingReceipts($filters)
    {
        $query = BookingReceipt::with(['booking' => function($q) use ($filters) {
            if (!empty($filters['crm_id'])) {
                $q->where('crm_id', 'like', '%' . $filters['crm_id'] . '%');
            }
        }]);

        $this->applyCommonFilters($query, $filters, true); // true = include sender

        return $query->get()->map(function($item) {
            return $this->formatReceipt($item, 'BookingReceipt');
        });
    }

    private function formatReceipt($item, $source)
    {
        // Determine the image/file field name based on the source
        $imageField = ($source === 'BookingReceipt') ? 'image' : 'file';

        $formatted = [
            'id' => $item->id,
            'table_source' => $source,
            'sender' => $item->sender ?? null,
            'amount' => $item->amount,
            'bank_name' => $item->bank_name,
            'date' => $item->date,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'receipt_url' => $item->{$imageField} ? Storage::url($source === 'BookingReceipt' ? 'images/' . $item->image : 'images/' . $item->file) : null,
            'receipt_type' => $source === 'BookingReceipt' ? 'customer_payment' : 'expense',
            'booking_id' => $source === 'BookingReceipt' ? ($item->booking ? $item->booking->id : null) : null,
            'reservation_id' => $source === 'ReservationExpenseReceipt' ? ($item->reservation ? $item->reservation->id : null) : null,
            'booking_item_id' => $source === 'ReservationExpenseReceipt' ? ($item->reservation ? $item->reservation->id : null) : null,
            'crm_id' => $source === 'BookingReceipt'
                ? ($item->booking ? $item->booking->crm_id : null)
                : ($item->reservation ? $item->reservation->crm_id : null),
        ];

        // Don't include toArray() as it breaks the object
        return $formatted;
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

        if (!empty($filters['crm_id'])) {
            if ($query->getModel() instanceof BookingReceipt) {
                $query->whereHas('booking', function($q) use ($filters) {
                    $q->where('crm_id', 'like', '%' . $filters['crm_id'] . '%');
                });
            } else if ($query->getModel() instanceof ReservationExpenseReceipt) {
                $query->whereHas('reservation', function($q) use ($filters) {
                    $q->where('crm_id', 'like', '%' . $filters['crm_id'] . '%');
                });
            }
        }
    }

    private function applyDateFilter($query, $date)
    {
        if (!$date) return;

        $dateArray = explode(',', $date);

        if (count($dateArray) === 2) {
            // Date range
            $startDate = trim($dateArray[0]);
            $endDate = trim($dateArray[1]);

            // Try different approaches based on column type
            // Approach 1: If date column is DATE or DATETIME
            $query->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate])
                  ->orWhereBetween('date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                  ->orWhere(function($subQ) use ($startDate, $endDate) {
                      $subQ->whereDate('date', '>=', $startDate)
                           ->whereDate('date', '<=', $endDate);
                  });
            });
        } else {
            // Single date
            $singleDate = trim($date);
            $query->where(function($q) use ($singleDate) {
                $q->whereDate('date', $singleDate)
                  ->orWhere('date', 'like', $singleDate . '%')
                  ->orWhere('date', $singleDate);
            });
        }
    }

    private function paginateResults($reservationReceipts, $bookingReceipts, $request, $perPage)
    {
        // Combine and convert to array first
        $allReceipts = $reservationReceipts->merge($bookingReceipts)
                                         ->sortByDesc('created_at')
                                         ->values()
                                         ->all(); // Convert to array

        // Pagination calculations
        $currentPage = (int) $request->get('page', 1);
        $total = count($allReceipts);
        $offset = ($currentPage - 1) * $perPage;
        $lastPage = ceil($total / $perPage);

        // Get paginated items
        $paginatedItems = array_slice($allReceipts, $offset, $perPage);

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
