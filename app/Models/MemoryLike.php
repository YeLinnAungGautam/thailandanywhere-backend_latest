<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryLike extends Model
{
    protected $guarded = [];

    public const TYPES = ['like', 'love'];

    public function memory()
    {
        return $this->belongsTo(Memory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
