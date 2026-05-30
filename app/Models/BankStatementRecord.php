<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatementRecord extends Model
{
    protected $fillable = [
        'month',
        'year',
        'account_number',
        'txn_date',
        'txn_time',
        'description',
        'withdrawal',
        'deposit',
        'balance',
        'channel',
        'detail',
        'cash_image_id',
        'duplicate_ids',
        'verified',
    ];

    protected $casts = [
        'txn_date'   => 'date',
        'withdrawal' => 'decimal:2',
        'deposit'    => 'decimal:2',
        'balance'    => 'decimal:2',
    ];

    /** The CashImage that was matched and verified. */
    public function cashImage()
    {
        return $this->belongsTo(CashImage::class);
    }
}
