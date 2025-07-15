<?php

namespace App\Services;

use App\Models\CashImage;

class CashImageBookingService
{
    public function addBookingsToCashImage($cashImageId, array $bookingsData)
    {
        $cashImage = CashImage::findOrFail($cashImageId);

        $syncData = [];
        foreach ($bookingsData as $bookingData) {
            $syncData[$bookingData['booking_id']] = [
                'deposit' => $bookingData['deposit'] ?? null,
                'notes' => $bookingData['notes'] ?? null,
            ];
        }

        $cashImage->bookings()->sync($syncData);

        return $cashImage->load('bookings');
    }

    public function updateBookingQuantity($cashImageId, $bookingId, $notes, $deposit = null)
    {
        $cashImage = CashImage::findOrFail($cashImageId);

        $cashImage->bookings()->updateExistingPivot($bookingId, [
            'deposit' => $deposit,
            'notes' => $notes
        ]);

        return $cashImage->load('bookings');
    }
}
