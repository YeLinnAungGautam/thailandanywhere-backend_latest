<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\BookingItemGroupResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
use App\Http\Resources\CashImageBookingResource;
use App\Http\Resources\TaxReceiptResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CashImageDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $relatable = null;
        $groupedItems = [];

        // FIXED: Handle both relatable_id > 0 and relatable_id = 0 cases
        if ($this->relatable_id > 0 && $this->relatable) {
            // Case 1: Polymorphic relationship (relatable_id > 0)
            switch ($this->relatable_type) {
                case 'App\Models\Booking':
                    $relatable = new BookingResource($this->relatable);
                    $groupedItems = $this->getGroupedBookingItems();
                    break;

                case 'App\Models\BookingItemGroup':
                    if (!$this->relatable->relationLoaded('bookingItems')) {
                        $this->relatable->load('bookingItems', 'customerDocuments', 'taxReceipts');
                    }
                    $relatable = new BookingItemGroupResource($this->relatable);
                    break;

                case 'App\Models\CashBook':
                    $relatable = new CashBookResource($this->relatable);
                    break;

                default:
                    $relatable = null;
            }
        }

        // FIXED: Get attached bookings from polymorphic many-to-many relationships
        $attachedBookings = $this->getAttachedBookings();

        return [
            'id' => $this->id,
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            'date' => $this->date ? $this->date->format('d-m-Y H:i:s') : null,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i:s') : null,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interact_bank' => $this->interact_bank,
            'relatable_type' => $this->relatable_type,
            'relatable_id' => $this->relatable_id,
            'relatable' => $relatable,
            'grouped_items' => $groupedItems,
            'attached_bookings' => $attachedBookings,
        ];
    }

    /**
     * Get attached bookings from polymorphic many-to-many relationships
     * FIXED: Use cashBookings instead of bookings
     */
    protected function getAttachedBookings()
    {
        $attachedBookings = [];

        // Get bookings from cash_imageables table (polymorphic many-to-many)
        if ($this->relationLoaded('cashBookings') && $this->cashBookings->count() > 0) {
            foreach ($this->cashBookings as $booking) {
                $attachedBookings[] = new BookingItemCashResource($booking);
            }
        }

        // Get booking item groups from cash_imageables table
        if ($this->relationLoaded('cashBookingItemGroups') && $this->cashBookingItemGroups->count() > 0) {
            foreach ($this->cashBookingItemGroups as $group) {
                if ($group->booking) {
                    // Check if this booking is not already in the array
                    $bookingId = $group->booking->id;
                    $exists = collect($attachedBookings)->contains(function($item) use ($bookingId) {
                        return isset($item->id) && $item->id === $bookingId;
                    });

                    if (!$exists) {
                        $attachedBookings[] = new BookingItemCashResource($group->booking);
                    }
                }
            }
        }

        // Get cash books from cash_imageables table
        if ($this->relationLoaded('cashBooks') && $this->cashBooks->count() > 0) {
            foreach ($this->cashBooks as $cashBook) {
                // You can add CashBook resource here if needed
                // $attachedBookings[] = new CashBookResource($cashBook);
            }
        }

        return $attachedBookings;
    }

    /**
     * Get booking items grouped by group_id
     */
    protected function getGroupedBookingItems()
    {
        try {
            // FIXED: Check both relatable_id and relatable existence
            if ($this->relatable_id == 0 || !$this->relatable || $this->relatable_type !== 'App\Models\Booking') {
                return [];
            }

            // Get all items from the booking with their groups and customer documents
            $items = $this->relatable->items()
                ->with([
                    'group',
                    'group.customerDocuments',
                    'group.cashImages',
                    'product',
                    'booking'
                ])
                ->get();

            // Group items by group_id
            $groupedItems = $items->groupBy('group_id');

            $result = [];

            foreach ($groupedItems as $groupId => $itemsInGroup) {
                // Get the group information
                $group = $itemsInGroup->first()->group ?? null;

                $result[] = [
                    'group_id' => $groupId,
                    'group_info' => $group ? new BookingItemGroupResource($group) : null,
                    'related_slip' => $group && $group->cashImages ? $group->cashImages : [],
                    'related_tax' => $group && $group->customerDocuments ?
                        CustomerDocumentResource::collection(
                            $group->customerDocuments->filter(function ($document) {
                                return $document->type == 'booking_confirm_letter';
                            })
                        ) : [],
                    'related_credit' => $group && $group->taxReceipts ?
                        TaxReceiptResource::collection($group->taxReceipts) : [],
                    'items_count' => $itemsInGroup->count(),
                    'items' => $itemsInGroup->map(function ($item) {
                        $variation_name = null;

                        if ($item->product_type == 'App\Models\Hotel') {
                            $variation_name = $item->room->name ?? null;
                        } elseif($item->product_type == 'App\Models\EntranceTicket') {
                            $variation_name = $item->variation->name ?? null;
                        } elseif($item->product_type == 'App\Models\PrivateVanTour') {
                            $variation_name = $item->car->name ?? null;
                        }

                        return [
                            'id' => $item->id,
                            'booking_id' => $item->booking->id ?? null,
                            'crm_id' => $item->crm_id,
                            'product_name' => $item->product->name ?? '-',
                            'product_type' => $item->product_type,
                            'variation_name' => $variation_name,
                            'service_date' => $item->service_date ? $item->service_date->format('Y-m-d') : null,
                            'sale_date' => $item->booking->booking_date ?? null,
                            'balance_due_date' => $item->booking->balance_due_date ?
                                $item->booking->balance_due_date->format('Y-m-d') : null,
                            'amount' => $item->amount,
                            'payment_status' => $item->booking->payment_status ?? null,
                            'expense_status' => $item->payment_status,
                            'payment_verify_status' => $item->booking->verify_status ?? null,
                            'income' => $item->amount - $item->total_cost_price,
                            'expense' => $item->total_cost_price,
                        ];
                    })->toArray(),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Error getting grouped booking items: " . $e->getMessage());
            return [];
        }
    }
}
