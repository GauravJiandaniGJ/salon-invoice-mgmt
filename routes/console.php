<?php

use Illuminate\Support\Facades\Schedule;

// Nightly backups (times in config('app.timezone') = Asia/Kolkata).
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run --only-db')->dailyAt('02:00');
Schedule::command('backup:run')->weeklyOn(0, '03:00'); // Sunday: DB + invoice PDFs + uploads
Schedule::command('activity:prune')->monthlyOn(1, '01:00'); // keep 12 months of audit history
