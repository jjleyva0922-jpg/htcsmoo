<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('smo:check-deadlines')
    ->dailyAt('08:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('notifications:prune --days=365')
    ->dailyAt('02:30')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
