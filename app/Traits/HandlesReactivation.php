<?php

namespace App\Traits;

use App\Mail\ReactivateAccountEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

trait HandlesReactivation
{
    protected function sendReactivationEmail($user)
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        Mail::to($user->email)->queue(new ReactivateAccountEmail($user, $token));
    }
}
