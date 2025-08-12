<?php

namespace App\Http\Resources\Accountance\Detail;

use App\Models\CustomerDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PrintResource extends JsonResource
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
            'receipt_image' => $this->receipt_image ? Storage::url('images/'. $this->receipt_image ) : null,
            'all_invoices' => $this->getAllInvoices(),
            'all_expenses' => $this->getAllExpenses(),
        ];
    }

    private function getAllInvoices() {
        if (!$this->relationLoaded('groups') || !$this->groups) {
            return [];
        }

        $images = [];

        $this->groups->each(function ($group) use (&$images) {
            if ($group->customerDocuments) {
                $expenseReceipts = $group->customerDocuments->where('type', 'booking_confirm_letter');

                foreach ($expenseReceipts as $receipt) {
                    $file_path = $receipt->file ? Storage::url(CustomerDocument::specificFolderPath($receipt->type) . $receipt->file) : null;
                    $images[] = [
                        'id' => $receipt->id,
                        'image' => $file_path,
                    ];
                }
            }
        });

        return $images;
    }

    private function getAllExpenses() {
        if (!$this->relationLoaded('groups') || !$this->groups) {
            return [];
        }

        $images = [];

        $this->groups->each(function ($group) use (&$images) {
            if ($group->customerDocuments) {
                $expenseReceipts = $group->customerDocuments->where('type', 'expense_receipt');

                foreach ($expenseReceipts as $receipt) {
                    $file_path = $receipt->file ? Storage::url(CustomerDocument::specificFolderPath($receipt->type) . $receipt->file) : null;
                    $images[] = [
                        'id' => $receipt->id,
                        'image' => $file_path,
                    ];
                }
            }
        });

        return $images;
    }
}
