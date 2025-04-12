<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountClass extends Model
{
    use HasFactory;

    protected $fillable = ['name','code','account_head_id'];

    public function accounts()
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class);
    }
}
