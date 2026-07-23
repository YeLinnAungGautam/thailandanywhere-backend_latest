<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    use HttpResponses;
    public function index(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'app_show_status' => 'string',
            'type' => 'sometimes|string|in:user,admin',
            'converted_only' => 'string',
            'start_date' => 'date_format:Y-m-d', // New: Date range filter (e.g., 2025-01-01)
            'end_date' => 'date_format:Y-m-d|after_or_equal:start_date', // Must be >= start_date
            'limit' => 'integer|min:1' // Added validation for limit
        ]);

        // Set defaults
        $limit = $validated['limit'] ?? 10;
        $userType = $validated['type'] ?? 'user';
        $appShowStatus = $validated['app_show_status'] ?? '';
        $convertedOnly = $validated['converted_only'] ?? 'false';
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        // Base query
        $query = Booking::when(in_array($userType, ['user', 'admin']), function($q) use ($userType) {
                $column = $userType === 'user' ? 'user_id' : 'created_by';
                $q->where($column, Auth::user()->id);
            })
            ->when($appShowStatus, function($q) use ($appShowStatus) {
                $q->filterByShowStatus($appShowStatus);
            })
            ->when($convertedOnly == 'true', function($q) {
                $q->whereHas('orders', function($subQuery) {
                    $subQuery->whereNotNull('booking_id');
                });
            })
            ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                // Date range filter: Bookings that overlap with the requested range
                $q->where(function($subQuery) use ($startDate, $endDate) {
                    $subQuery->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function($q) use ($startDate, $endDate) {
                            // Case where booking spans the entire range
                            $q->where('start_date', '<=', $startDate)
                              ->where('end_date', '>=', $endDate);
                        });
                });
            })
            ->orderBy('start_date', 'asc');

        // Get paginated results
        $bookings = $query->with(['orders', 'items'])
            ->latest()
            ->paginate($limit);

        // Count booking items by product type
        $productTypeCounts = [];
        if ($bookings->isNotEmpty()) {
            $bookingIds = $bookings->pluck('id');
            $productTypeCounts = DB::table('booking_items')
                ->whereIn('booking_id', $bookingIds)
                ->select('product_type', DB::raw('count(*) as count'))
                ->groupBy('product_type')
                ->pluck('count', 'product_type')
                ->toArray();
        }

        // Prepare response
        return $this->success(
            BookingResource::collection($bookings)->additional([
                'meta' => [
                    'total_page' => $bookings->lastPage(),
                    'product_type_counts' => $productTypeCounts,
                ]
            ])->response()
            ->getData(),
            'Bookings retrieved'
        );
    }

    public function show(string $id)
    {
        $find = Booking::find($id);

        if (!$find) {
            return failedMessage('Data not found');
        }

        return $this->success(new BookingResource($find), 'Data retrieved successfully');
    }

    public function store(Request $request, $id)
    {
        $booking = Booking::with('customer')->find($id);
        if (!$booking) {
            return failedMessage('Booking not found');
        }

        $request->validate([
            'customer_name' => 'required|string',
            'phone_number'  => 'required|string',
        ]);

        // Already handled by this account?
        if ($booking->user_id == Auth::user()->id) {
            return failedMessage('This booking is already assigned with your account, Go to Trip to see booking details');
        }

        // Already claimed by someone else?
        if ($booking->user_id) {
            return failedMessage('This booking is already assigned to a user');
        }

        $customer = $booking->customer;
        if (!$customer) {
            return failedMessage('Customer information not found for this booking');
        }

        // Normalize before comparing so formatting differences don't block a real match
        $normalizePhone = fn ($v) => preg_replace('/[^0-9]/', '', (string) $v);
        $normalizeName  = fn ($v) => strtolower(trim((string) $v));

        $nameMatches  = $normalizeName($customer->name) === $normalizeName($request->customer_name);
        $phoneDigits  = $normalizePhone($request->phone_number);
        $phoneMatches = $phoneDigits !== '' && str_ends_with($normalizePhone($customer->phone_number), $phoneDigits);
        // str_ends_with handles cases like customer has "+959..." but user typed without country code

        if (!$nameMatches || !$phoneMatches) {
            return failedMessage('Customer name or phone number does not match our records');
        }

        $booking->user_id = Auth::user()->id;
        $booking->save();

        return $this->success(new BookingResource($booking), 'Booking connected to your account successfully');
    }

    public function checkBookingHasUser($id)
    {
        // Find the booking by ID
        $booking = Booking::find($id);

        // If booking doesn't exist, return error response
        if (!$booking) {
            return failedMessage('Booking not found');
        }

        // Check if booking has a user assigned
        $hasUser = !is_null($booking->user_id);

        // Return appropriate response based on your application's format
        if ($hasUser) {
            return success([
                'booking_id' => $booking->id,
                'has_user' => true
            ], 'Booking is already assigned to a user');
        } else {
            return success([
                'booking_id' => $booking->id,
                'has_user' => false
            ], 'Booking has no user assigned');
        }
    }

    public function getBookingDetail(string $id)
    {
        $booking = Booking::with([
            'customer',
            'items.product' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Hotel::class => ['images'],
                ]);
            },
            'items.car',
            'items.room',
            'items.variation',
            'items.ticket',
            'items.groupTour',
        ])->find($id);

        if (!$booking) {
            return $this->error(null, 'Booking not found', 404);
        }

        $mapItem = function ($item) {
            $isEntranceTicket = $item->product_type === \App\Models\EntranceTicket::class;

            if ($isEntranceTicket) {
                $adultQty   = (int) ($item->adult_quantity ?? 0);
                $childQty   = (int) ($item->child_quantity ?? 0);
                $adultPrice = (float) ($item->adult_price ?? 0);
                $childPrice = (float) ($item->child_price ?? 0);

                $quantityLabel = trim(
                    ($adultQty > 0 ? "{$adultQty}A" : '') .
                    ($adultQty > 0 && $childQty > 0 ? '-' : '') .
                    ($childQty > 0 ? "{$childQty}C" : ''),
                    '-'
                );

                $priceLabel = trim(
                    ($adultPrice > 0 ? number_format($adultPrice, 0) . 'A' : '') .
                    ($adultPrice > 0 && $childPrice > 0 ? ' - ' : '') .
                    ($childPrice > 0 ? number_format($childPrice, 0) . 'C' : '')
                );

                return [
                    'id'              => $item->id,
                    'product_type'    => $item->acsr_product_type_name,
                    'product_id'      => $item->product_id,
                    'name'            => $item->product->name ?? null,
                    'image'           => $this->resolveItemImage($item),
                    'variation_label' => $item->acsr_variation_name,
                    'service_date'    => $item->service_date
                        ? Carbon::parse($item->service_date)->format('d F Y')
                        : null,
                    'quantity'        => $quantityLabel,
                    'price'           => $priceLabel,
                    'discount'        => (float) ($item->discount ?? 0),
                    'amount'          => (float) ($item->amount ?? 0),
                ];
            }

            return [
                'id'              => $item->id,
                'product_type'    => $item->acsr_product_type_name,
                'product_id'      => $item->product_id,
                'name'            => $item->product->name ?? null,
                'image'           => $this->resolveItemImage($item),
                'variation_label' => $item->acsr_variation_name,
                'service_date'    => $item->service_date
                    ? Carbon::parse($item->service_date)->format('d F Y')
                    : null,
                'quantity'        => $item->quantity,
                'price'           => (float) ($item->selling_price ?? $item->amount ?? 0),
                'discount'        => (float) ($item->discount ?? 0),
                'amount'          => (float) ($item->amount ?? 0),
            ];
        };

        $isInclusive = (bool) $booking->is_inclusive;

        if ($isInclusive) {
            $children = $booking->items->map($mapItem)->values();

            $items = collect([[
                'id'              => 'inclusive',
                'product_type'    => 'Inclusive',
                'product_id'      => null,
                'name'            => $booking->inclusive_name,
                'image'           => null,
                'variation_label' => $booking->inclusive_description != 'null'? $booking->inclusive_description : '-',
                'service_date'    => $booking->inclusive_start_date && $booking->inclusive_end_date
                    ? Carbon::parse($booking->inclusive_start_date)->format('d F Y')
                      . ' – '
                      . Carbon::parse($booking->inclusive_end_date)->format('d F Y')
                    : null,
                'quantity'        => $booking->inclusive_quantity,
                'price'           => (float) ($booking->inclusive_rate ?? 0),
                'discount'        => 0,
                'amount'          => (float) ($booking->inclusive_rate ?? 0) * (int) ($booking->inclusive_quantity ?? 1),
                'children'        => $children,
            ]]);

            $itemCount = $children->count();
        } else {
            $items = $booking->items->map($mapItem);
            $itemCount = $items->count();
        }

        return $this->success([
            'id' => $booking->id,
            'invoice_number' => $booking->invoice_number,
            'crm_id' => $booking->crm_id,
            'payment_status' => $booking->payment_status,
            'customer_name' => $booking->customer->name ?? '-',
            'has_user' => !is_null($booking->user), // NEW: tell frontend if already connected
            'sales_date' => $booking->booking_date
                ? Carbon::parse($booking->booking_date)->format('d F Y')
                : null,
            'due_date' => $booking->balance_due_date
                ? Carbon::parse($booking->balance_due_date)->format('d F Y')
                : null,
            'item_count' => $itemCount,
            'items' => $items,
            'sub_total' => (float) $booking->sub_total,
            'discount' => (float) $booking->discount,
            'grand_total' => (float) $booking->grand_total,
            'deposit' => (float) $booking->deposit,
            'balance_due' => (float) $booking->balance_due,
            'payment_due_status' => $booking->payment_status,
        ], 'Booking detail retrieved');
    }

    /**
     * Resolve a thumbnail image for the item card from the live product.
     */
    private function resolveItemImage($item)
    {
        $product = $item->product;

        if (!$product) {
            return null;
        }

        if ($item->product_type === \App\Models\Hotel::class && method_exists($product, 'images')) {
            $firstImage = $product->relationLoaded('images')
                ? $product->images->first()
                : $product->images()->first();

            return $firstImage ? Storage::url('images/' . $firstImage->image) : null;
        }

        $image = $product->cover_image ?? $product->image ?? null;

        return $image ? Storage::url('images/' . $image) : null;
    }


}
