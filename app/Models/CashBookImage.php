<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashBookImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'cash_book_id',
    ];
}
