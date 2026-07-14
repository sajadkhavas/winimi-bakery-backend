<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sitemap:generate')->dailyAt('03:00');
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::command('queue:prune-batches')->daily();
