<?php

use App\Jobs\SyncCatalog;
use Illuminate\Support\Facades\Schedule;

// The ERP export is refreshed every 15 minutes; the job is unique, so a slow run never overlaps the next.
Schedule::job(new SyncCatalog)->everyFifteenMinutes();

// Independent of the job: catches the sync that silently stopped running.
Schedule::command('el3ktron:check-sync-freshness')->everyFiveMinutes();
