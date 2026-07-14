<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('adn:dispatch-due-syncs')->everyMinute();
Schedule::command('exports:purge-expired')->hourly();
