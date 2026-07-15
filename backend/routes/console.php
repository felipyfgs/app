<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('adn:dispatch-due-syncs')->everyMinute();
Schedule::command('sefaz:dispatch-due-syncs')->everyMinute();
Schedule::command('sefaz:dispatch-ma-outbound-due')->everyMinute();
Schedule::command('exports:purge-expired')->hourly();
Schedule::command('credentials:refresh-expiry')->hourly();

if (config('backup.schedule_enabled')) {
    Schedule::command('ops:backup-run --kind=full')->dailyAt('02:15');
}
