<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

/**
 * Lab 7 - scheduled inventory checks.
 *
 * Laravel 12 no longer boots a Console Kernel automatically; this class keeps
 * the familiar `schedule()` method from the lab instructions and is invoked
 * from routes/console.php so `php artisan schedule:run` still picks it up.
 */
class Kernel
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->command('inventory:check-reorder-points')
            ->dailyAt('02:00')
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'))
            ->onFailure(function () {
                Log::error('Scheduled reorder check (inventory:check-reorder-points) failed.');
            });
    }
}
