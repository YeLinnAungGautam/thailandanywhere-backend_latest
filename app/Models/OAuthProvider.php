<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthProvider extends Model
{
    protected $table = "o_auth_providers";

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
