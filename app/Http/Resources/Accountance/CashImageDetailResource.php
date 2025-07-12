<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\BookingItemGroupResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
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

            // Check if relatable relationship exists before processing
            if ($this->relatable) {
                switch ($this->relatable_type) {
                    case 'App\Models\Booking':
                        $relatable = new BookingResource($this->relatable);
                        // Get grouped items for booking
                        $groupedItems = $this->getGroupedBookingItems();
                        break;
                    case 'App\Models\BookingItemGroup':
                        $relatable = new BookingItemGroupResource($this->relatable);
                        break;
                    case 'App\Models\CashBook':
                        $relatable = new CashBookResource($this->relatable);
                        break;
                    default:
                        // Handle unknown relatable types
                        $relatable = null;
                }
            }

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
            ];
        }

        /**
         * Get booking items grouped by group_id
         *
         * @return array
         */
        protected function getGroupedBookingItems()
        {
            try {
                if (!$this->relatable || $this->relatable_type !== 'App\Models\Booking') {
                    return [];
                }

                // Get all items from the booking with their groups and customer documents
                $items = $this->relatable->items()->with(['group', 'group.customerDocuments', 'group.cashImages', 'product', 'booking'])->get();

                // Group items by group_id
                $groupedItems = $items->groupBy('group_id');

                $result = [];

                foreach ($groupedItems as $groupId => $itemsInGroup) {
                    // Get the group information (assuming first item has the group relationship loaded)
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
                                'balance_due_date' => $item->booking->balance_due_date ? $item->booking->balance_due_date->format('Y-m-d') : null,
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
