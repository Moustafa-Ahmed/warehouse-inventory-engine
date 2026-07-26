<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shipments:process-pending --limit=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('provider-submissions:reconcile-unknown --limit=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('mock-provider:dispatch-pending --limit=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('provider-webhooks:process-pending --limit=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('inventory:allocate-backorders --batch=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('reservations:expire --batch=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();
