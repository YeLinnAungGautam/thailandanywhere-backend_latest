<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'date',
        'sender',
        'receiver',
        'amount',
        'interact_bank',
        'currency',
        'relatable_type',
        'relatable_id'
    ];

    protected $casts = [
        'date' => 'datetime', // Changed to datetime for date and time
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Polymorphic relationship
    public function relatable()
    {
        return $this->morphTo();
    }
}
