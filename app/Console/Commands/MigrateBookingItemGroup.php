<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingItemGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateBookingItemGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:booking-item-group';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->upsertBookingItemGroup();

        $this->migrateCustomerPassport();

        $this->migrateBookingRequestDocuments();

        $this->migrateBookingConfirmLetters();

        $this->migrateExpenseReceipts();

        $this->migrateExpenseMails();

        $this->migratePaidSlips();

        $this->migrateCarInfos();

        $this->migrateSupplierInfos();

        $this->migrateTaxSlips();
    }

    private function upsertBookingItemGroup()
    {
        Booking::with('items')->chunk(100, function ($bookings) {
            foreach ($bookings as $booking) {
                $grouped = $booking->items->groupBy(function ($item) {
                    return $item->product_type . ':' . $item->product_id;
                });

                foreach ($grouped as $key => $items) {
                    [$product_type, $product_id] = explode(':', $key);

                    $total_cost_price = $items->sum('cost_price');

                    $group = BookingItemGroup::updateOrCreate(
                        [
                            'booking_id' => $booking->id,
                            'product_type' => $product_type,
                            'product_id' => $product_id,
                        ],
                        [
                            'total_cost_price' => $total_cost_price,
                        ]
                    );

                    foreach ($items as $item) {
                        $item->update(['group_id' => $group->id]);
                    }
                }
            }
        });
    }

    private function migrateCustomerPassport()
    {
        DB::table('reservation_customer_passports')->orderBy('id')->chunk(100, function ($passports) {
            foreach ($passports as $passport) {
                $bookingItem = BookingItem::find($passport->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                if (is_null($passport->name) && is_null($passport->passport_number) && is_null($passport->dob)) {
                    $meta = null;
                } else {
                    $meta = [
                        'name' => $passport->name,
                        'passport_number' => $passport->passport_number,
                        'dob' => $passport->dob,
                    ];
                }

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'passport',
                ], [
                    'file' => $passport->file,
                    'meta' => $meta ? json_encode($meta) : null,
                ]);
            }
        });
    }

    private function migrateBookingRequestDocuments()
    {
        DB::table('reservation_booking_requests')->orderBy('id')->chunk(100, function ($requests) {
            foreach ($requests as $request) {
                $bookingItem = BookingItem::find($request->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'is_approved' => $bookingItem->is_booking_request,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'booking_request',
                    'file' => $request->file,
                ], [
                    'file_name' => $request->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }

    private function migrateBookingConfirmLetters()
    {
        DB::table('reservation_booking_confirm_letters')->orderBy('id')->chunk(100, function ($letters) {
            foreach ($letters as $letter) {
                $bookingItem = BookingItem::find($letter->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'amount' => $letter->amount ?? null,
                    'invoice' => $letter->invoice ?? null,
                    'due_date' => $letter->due_date ?? null,
                    'customer' => $letter->customer ?? null,
                    'sender_name' => $letter->sender_name ?? null,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'booking_confirm_letter',
                    'file' => $letter->file,
                ], [
                    'file_name' => $letter->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }

    private function migrateExpenseReceipts()
    {
        DB::table('reservation_expense_receipts')->orderBy('id')->chunk(100, function ($receipts) {
            foreach ($receipts as $receipt) {
                $bookingItem = BookingItem::find($receipt->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'amount' => $receipt?->amount,
                    'bank_name' => $receipt?->bank_name,
                    'date' => $receipt?->date,
                    'is_corporate' => $receipt?->is_corporate,
                    'comment' => $receipt?->comment,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'expense_receipt',
                    'file' => $receipt->file,
                ], [
                    'file_name' => $receipt->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }

    private function migrateExpenseMails()
    {
        DB::table('reservation_expense_mails')->orderBy('id')->chunk(100, function ($mails) {
            foreach ($mails as $mail) {
                $bookingItem = BookingItem::find($mail->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }
                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'expense_mail',
                    'file' => $mail->file,
                ], [
                    'file_name' => $mail->file,
                    'meta' => null,
                ]);
            }
        });
    }

    private function migratePaidSlips()
    {
        DB::table('reservation_paid_slips')->orderBy('id')->chunk(100, function ($slips) {
            foreach ($slips as $slip) {
                $bookingItem = BookingItem::find($slip->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }
                $meta = [
                    'amount' => $slip->amount,
                ];
                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'paid_slip',
                    'file' => $slip->file,
                ], [
                    'file_name' => $slip->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }

    private function migrateCarInfos()
    {
        DB::table('reservation_car_infos')->orderBy('id')->chunk(100, function ($infos) {
            foreach ($infos as $info) {
                $bookingItem = BookingItem::find($info->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }
                $meta = [
                    'driver_contact' => $info->driver_contact,
                    'account_holder_name' => $info->account_holder_name,
                    'supplier_id' => $info->supplier_id,
                    'driver_id' => $info->driver_id,
                    'driver_info_id' => $info->driver_info_id,
                ];
                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'car_info',
                    'file' => $info->car_photo,
                ], [
                    'file_name' => $info->car_photo,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }

    private function migrateSupplierInfos()
    {
        DB::table('reservation_supplier_infos')->orderBy('id')->chunk(100, function ($infos) {
            foreach ($infos as $info) {
                $bookingItem = BookingItem::find($info->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }
                $meta = [
                    'ref_number' => $info->ref_number,
                    'supplier_name' => $info->supplier_name,
                ];
                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'supplier_info',
                ], [
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }

    private function migrateTaxSlips()
    {
        DB::table('reservation_tax_slips')->orderBy('id')->chunk(100, function ($slips) {
            foreach ($slips as $slip) {
                $bookingItem = BookingItem::find($slip->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }
                $meta = [
                    'amount' => $slip->amount,
                    'issue_date' => $slip->issue_date,
                ];
                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'tax_slip',
                    'file' => $slip->file,
                ], [
                    'file_name' => $slip->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }
}
