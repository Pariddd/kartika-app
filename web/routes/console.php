<?php

use Illuminate\Support\Facades\Schedule;

// Butuh "php artisan schedule:work" (lokal) atau cron "schedule:run" tiap menit (server).
Schedule::command('radar:purge-expired')->hourly()->withoutOverlapping();
