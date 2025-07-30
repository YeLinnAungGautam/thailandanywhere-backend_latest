<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\BookingItemGroupResource;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
use App\Http\Resources\TaxReceiptResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class BookingCashImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'crm_id' => $this->crm_id,
            'customer' => $this->customer,
            'user' => $this->user,
            'payment_currency' => $this->payment_currency,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'booking_date' => $this->booking_date,
            'money_exchange_rate' => $this->money_exchange_rate,
            'sub_total' => $this->sub_total,
            'grand_total' => $this->grand_total,
            'deposit' => $this->deposit,
            'discount' => $this->discount,
            'balance_due' => $this->balance_due,
            'balance_due_date' => $this->balance_due_date ? $this->balance_due_date->format('Y-m-d') : null,
            'reservation_status' => $this->reservation_status,
            'verify_status' => $this->verify_status,

            // VAT and Commission
            'output_vat' => $this->output_vat,
            'commission' => $this->commission,

            // Invoice information
            'has_invoice' => !is_null($this->invoice_number),
            'invoice_info' => [
                'number' => $this->invoice_number,
                'status' => $this->payment_status,
                'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
                'has_tax_receipt' => $this->items()->whereHas('group.taxReceipts')->exists(),
                'has_customer_documents' => $this->items()->whereHas('group.customerDocuments')->exists(),
            ],

            // Grouped items with enhanced information
            'grouped_items' => $this->getGroupedBookingItems(),
            'grouped_items_summary' => $this->getGroupedItemsSummary(),

            // Financial breakdown
            'financial_breakdown' => [
                'sub_total' => $this->sub_total,
                'discount' => $this->discount,
                'discount_percentage' => $this->sub_total > 0 ? round(($this->discount / $this->sub_total) * 100, 2) : 0,
                'output_vat' => $this->output_vat,
                'vat_percentage' => $this->sub_total > 0 ? round(($this->output_vat / $this->sub_total) * 100, 2) : 0,
                'commission' => $this->commission,
                'commission_percentage' => $this->sub_total > 0 ? round(($this->commission / $this->sub_total) * 100, 2) : 0,
                'grand_total' => $this->grand_total,
                'deposit' => $this->deposit,
                'deposit_percentage' => $this->grand_total > 0 ? round(($this->deposit / $this->grand_total) * 100, 2) : 0,
                'balance_due' => $this->balance_due,
                'balance_percentage' => $this->grand_total > 0 ? round(($this->balance_due / $this->grand_total) * 100, 2) : 0,
            ],

            // Payment information
            'payment_info' => [
                'currency' => $this->payment_currency,
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'exchange_rate' => $this->money_exchange_rate,
                'verify_status' => $this->verify_status,
            ],

            // Timestamps
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i:s') : null,
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
            // Get all items from the booking with their groups and related data
            $items = $this->items()->with([
                'group',
                'group.customerDocuments',
                'group.cashImages',
                'group.taxReceipts',
                'product',
                'booking',
                'room',
                'variation',
                'car'
            ])->get();

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
                    'group_total_amount' => $itemsInGroup->sum('amount'),
                    'group_total_cost' => $itemsInGroup->sum('total_cost_price'),
                    'group_income' => $itemsInGroup->sum('amount') - $itemsInGroup->sum('total_cost_price'),
                    'group_profit_margin' => $itemsInGroup->sum('amount') > 0 ?
                        round((($itemsInGroup->sum('amount') - $itemsInGroup->sum('total_cost_price')) / $itemsInGroup->sum('amount')) * 100, 2) : 0,
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
                            'cost_price' => $item->total_cost_price,
                            'profit' => $item->amount - $item->total_cost_price,
                            'profit_margin' => $item->amount > 0 ? round((($item->amount - $item->total_cost_price) / $item->amount) * 100, 2) : 0,
                            'payment_status' => $item->booking->payment_status ?? null,
                            'expense_status' => $item->payment_status,
                            'payment_verify_status' => $item->booking->verify_status ?? null,
                        ];
                    })->toArray(),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Error getting grouped booking items in BookingCashImageResource: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get summary of grouped items
     *
     * @return array
     */
    protected function getGroupedItemsSummary()
    {
        try {
            $items = $this->items()->get();
            $groupedItems = $items->groupBy('group_id');

            return [
                'total_groups' => $groupedItems->count(),
                'total_items' => $items->count(),
                'total_amount' => $items->sum('amount'),
                'total_cost' => $items->sum('total_cost_price'),
                'total_profit' => $items->sum('amount') - $items->sum('total_cost_price'),
                'average_profit_margin' => $items->sum('amount') > 0 ?
                    round((($items->sum('amount') - $items->sum('total_cost_price')) / $items->sum('amount')) * 100, 2) : 0,
                'groups_with_documents' => $groupedItems->filter(function ($group) {
                    $firstItem = $group->first();
                    return $firstItem && $firstItem->group && $firstItem->group->customerDocuments()->exists();
                })->count(),
                'groups_with_tax_receipts' => $groupedItems->filter(function ($group) {
                    $firstItem = $group->first();
                    return $firstItem && $firstItem->group && $firstItem->group->taxReceipts()->exists();
                })->count(),
            ];

        } catch (\Exception $e) {
            Log::error("Error getting grouped items summary in BookingCashImageResource: " . $e->getMessage());
            return [];
        }
    }
}
