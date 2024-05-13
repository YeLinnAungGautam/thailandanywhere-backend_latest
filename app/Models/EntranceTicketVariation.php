<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EntranceTicketVariation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'cost_price' => 'integer',
    ];

    public function entranceTicket(): BelongsTo
    {
        return $this->belongsTo(EntranceTicket::class, 'entrance_ticket_id');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(ProductImage::class, 'ownerable');
    }
}
