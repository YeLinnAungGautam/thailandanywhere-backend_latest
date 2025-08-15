<?php
namespace App\Traits;

use App\Models\CashImage;

trait HasCashImages
{
    public function bCashImages()
    {
        return $this->morphToMany(CashImage::class, 'imageable', 'cash_imageables')
            ->withPivot(['type', 'deposit', 'notes'])
            ->withTimestamps();
    }

    public function addCashImage($cashImageId, array $pivotData = [])
    {
        return $this->bCashImages()->attach($cashImageId, $pivotData);
    }

    public function updateCashImage($cashImageId, array $pivotData = [])
    {
        return $this->bCashImages()->updateExistingPivot($cashImageId, $pivotData);
    }

    public function removeCashImage($cashImageId)
    {
        return $this->bCashImages()->detach($cashImageId);
    }
}
