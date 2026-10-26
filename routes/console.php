<?php

use Illuminate\Support\Facades\Schedule;

// Durées de conservation (E-04) : chaque nuit, à une heure creuse.
Schedule::command('makary:purger')->dailyAt('02:30')->timezone(config('app.timezone'))->withoutOverlapping();
