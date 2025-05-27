<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'order_datetime' => 'datetime',
        'expire_datetime' => 'datetime',
    ];

    // ဆက်စပ်မှုများ
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // အခြေအနေစစ်ဆေးခြင်း
    public function isExpired()
    {
        return Carbon::now()->isAfter($this->expire_datetime);
    }
}
