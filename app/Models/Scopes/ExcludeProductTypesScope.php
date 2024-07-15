<?php

namespace App\Models\Scopes;

use App\Models\Airline;
use App\Models\AirportPickup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ExcludeProductTypesScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof \App\Models\Booking) {
            // $builder->whereIn('id', function ($query) {
            //     $query
            //         ->select('booking_id')
            //         ->from('booking_items')
            //         ->whereNotIn('product_type', ['airline', 'airport_pickup']);
            // });

            $builder->whereHas('items', function ($query) {
                $query->whereNotIn('product_type', ['airline', 'airport_pickup']);
            });
        } elseif ($model instanceof \App\Models\BookingItem) {
            $builder->whereNotIn('product_type', [Airline::class, AirportPickup::class]);
        }
    }
}
