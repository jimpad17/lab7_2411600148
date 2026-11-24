<?php

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Laravel 12 registers scheduled tasks here. The definitions themselves live
 * in App\Console\Kernel so they remain recognizable from the lab instructions.
 */
app(Kernel::class)->schedule(app(Schedule::class));
