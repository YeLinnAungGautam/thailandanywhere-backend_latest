<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $now = Carbon::now();

        // Order Status Update
        Order::with('orderItems')->chunk(200, function ($orders) use ($now) {
            foreach ($orders as $order) {
                // Skip cancelled orders
                if ($order->order_status === 'cancelled') {
                    $order->update(['app_show_status' => 'cancelled']);
                    continue;
                }

                $items = $order->orderItems;

                // No items case
                if ($items->isEmpty()) {
                    $order->update(['app_show_status' => 'upcoming']);
                    continue;
                }

                // Check service dates for all items
                $allCompleted = true;
                $allUpcoming = true;

                foreach ($items as $item) {
                    if ($item->service_date >= $now) {
                        $allCompleted = false;
                    } else {
                        $allUpcoming = false;
                    }
                }

                // Determine status
                if ($allUpcoming) {
                    $order->update(['app_show_status' => 'upcoming']);
                } elseif ($allCompleted && $order->order_status !== 'pending') {
                    $order->update(['app_show_status' => 'completed']);
                } else {
                    // Mixed status or pending orders
                    $order->update(['app_show_status' => 'ongoing']);
                }
            }
        });

        // Booking Status Update (similar logic)
        Booking::with('items')->chunk(200, function ($bookings) use ($now) {
            foreach ($bookings as $booking) {
                if ($booking->payment_status === 'cancelled') {
                    $booking->update(['app_show_status' => 'cancelled']);
                    continue;
                }

                $items = $booking->items;

                if ($items->isEmpty()) {
                    $booking->update(['app_show_status' => 'upcoming']);
                    continue;
                }

                $allCompleted = true;
                $allUpcoming = true;

                foreach ($items as $item) {
                    if ($item->service_date >= $now) {
                        $allCompleted = false;
                    } else {
                        $allUpcoming = false;
                    }
                }

                if ($allUpcoming) {
                    $booking->update(['app_show_status' => 'upcoming']);
                } elseif ($allCompleted && $booking->payment_status !== 'not_paid') {
                    $booking->update(['app_show_status' => 'completed']);
                } else {
                    $booking->update(['app_show_status' => 'ongoing']);
                }
            }
        });
    }
}
