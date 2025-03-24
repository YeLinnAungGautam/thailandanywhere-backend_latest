<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'account_name',
        'account_class_id',
        'account_head_id',
        'product_type'
    ];

    public function accountClass()
    {
        return $this->belongsTo(AccountClass::class);
    }

    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class);
    }
}
