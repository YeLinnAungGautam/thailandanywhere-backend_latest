<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteExpiredCartItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-expired-cart-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired cart items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        $expiredCartItems = Cart::whereNotNull('service_date')
        ->where('service_date', '<', $today)
        ->get();

        $count = $expiredCartItems->count();

        foreach ($expiredCartItems as $cart) {
            $cart->delete();
        }

        $this->info("Deleted $count expired cart items");

        return Command::SUCCESS;
    }
}
