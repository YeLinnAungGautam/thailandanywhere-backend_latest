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

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {

        // Order Status Update
        Order::whereIn('app_show_status', [null, 'upcoming', 'cancelled'])
             ->chunk(200, function ($orders) {
                foreach ($orders as $order) {
                    if ($order->order_status === 'cancelled') {
                        $order->update(['app_show_status' => 'cancelled']);
                        continue;
                    }

                    $hasUpcomingItems = $order->orderItems()
                        ->where('service_date', '>=', Carbon::now())
                        ->exists();

                    $hasCompletedItems = $order->orderItems()
                        ->where('service_date', '<', Carbon::now())
                        ->exists();

                    if ($hasUpcomingItems && !$hasCompletedItems) {
                        $order->update(['app_show_status' => 'upcoming']);
                    } elseif ($hasCompletedItems && !in_array($order->order_status, ['pending', 'cancelled'])) {
                        $order->update(['app_show_status' => 'completed']);
                    }
                }
             });

        // Booking Status Update
        Booking::whereIn('app_show_status', [null, 'upcoming', 'ongoing'])
               ->chunk(200, function ($bookings) {
                foreach ($bookings as $booking) {
                    if ($booking->payment_status === 'cancelled') {
                        $booking->update(['app_show_status' => 'cancelled']);
                        continue;
                    }

                    $allItemsCount = $booking->items()->count();
                    $upcomingItemsCount = $booking->items()
                        ->where('service_date', '>=', Carbon::now())
                        ->count();
                    $completedItemsCount = $booking->items()
                        ->where('service_date', '<', Carbon::now())
                        ->count();

                    if ($upcomingItemsCount === $allItemsCount) {
                        $booking->update(['app_show_status' => 'upcoming']);
                    } elseif ($completedItemsCount === $allItemsCount && $booking->payment_status !== 'not_paid') {
                        $booking->update(['app_show_status' => 'completed']);
                    } elseif ($completedItemsCount > 0 && $booking->payment_status !== 'not_paid') {
                        $booking->update(['app_show_status' => 'ongoing']);
                    }
                }
               });
    }
}
