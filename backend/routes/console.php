<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('adn:dispatch-due-syncs')->everyMinute();
Schedule::command('sefaz:dispatch-due-syncs')->everyMinute();
Schedule::command('sefaz:dispatch-due-autxml')->everyMinute();
Schedule::command('sefaz:dispatch-due-cte-autxml')->everyMinute();
Schedule::command('sefaz:dispatch-ma-outbound-due')->everyMinute();
Schedule::command('sefaz:dispatch-svrs-nfce-xml-recoveries')->everyMinute();
Schedule::command('fiscal:dispatch-due-monitoring')->everyMinute();
Schedule::command('outbound:deadline-plan')->hourly();
Schedule::command('exports:purge-expired')->hourly();
Schedule::command('import:purge-expired-spools')->hourly();
Schedule::command('credentials:refresh-expiry')->hourly();

if (config('backup.schedule_enabled')) {
    Schedule::command('ops:backup-run --kind=full')->dailyAt('02:15');
}
