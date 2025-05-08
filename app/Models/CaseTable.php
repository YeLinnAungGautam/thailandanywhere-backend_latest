<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseTable extends Model
{
    use HasFactory;

    protected $table = 'case_tables';

    protected $fillable = [
        'related_id',
        'case_type',
        'verification_status',
        'name',
        'detail',
    ];

    protected $casts = [
        'verification_status' => 'string',
        'case_type' => 'string',
    ];

    public function related()
    {
        return $this->case_type === 'sale'
            ? $this->belongsTo(Booking::class, 'related_id')
            : $this->belongsTo(BookingItem::class, 'related_id');
    }
}
