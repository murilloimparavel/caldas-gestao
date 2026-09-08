<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:renew-customer-subscriptions')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('retention:anonymize --limit=1000')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('retention:campaign-audience')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('retention:campaign-dispatch')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('retention:campaign-process')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('app:reconcile-tenant-domains')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
