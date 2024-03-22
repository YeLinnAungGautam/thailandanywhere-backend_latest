<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function driverInfos(): HasMany
    {
        return $this->hasMany(DriverInfo::class);
    }

    public function defaultInfo()
    {
        return $this->driverInfos()->where('is_default', 1)->first();
    }

    public function hasDefaultInfo(): bool
    {
        return $this->driverInfos()->where('is_default', 1)->exists();
    }
}
