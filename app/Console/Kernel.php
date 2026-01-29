<?php

namespace App\Console;

use App\Services\OrderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('db:backup')->daily();

        $schedule->command('send:reservation-report daily')->dailyAt('9:00');
        $schedule->command('send:reservation-report weekly')->weeklyOn(1, '9:00');

        $schedule->command('status:update')->dailyAt('3:00');

        $schedule->command('users:delete-unverified')->everyFiveMinutes();

        $schedule->command('app:delete-expired-cart-items')->daily();

        $schedule->command('sessions:cleanup')->daily();

        $schedule->call(function () {
            (new OrderService)->cleanupExpiredOrders();
        })->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
