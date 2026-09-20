<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Early morning, so a "sow this month" nudge lands before someone heads out
// to the garden rather than after. Safe to run more than once: every send is
// guarded by a unique row.
Schedule::command('garden:reminders')->dailyAt('06:30')->withoutOverlapping();
