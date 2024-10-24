<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserUUIDSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereNull('unique_key')->get();
        foreach ($users as $user) {
            $user->unique_key = Str::uuid();
            $user->save();
        }
    }
}
