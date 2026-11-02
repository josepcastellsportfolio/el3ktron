<?php

namespace App\Console\Commands;

use App\Alerts\AlertService;
use App\Catalog\ErpFeed;
use App\Enums\AlertSeverity;
use App\Models\SyncRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The alert for the failure nobody sees: the sync that stops running at all
 * (worker down, queue stuck, cron gone). A failing sync raises its own alert;
 * this one fires when there is no recent success, whatever the reason, and
 * resolves itself when syncs come back.
 */
#[Signature('el3ktron:check-sync-freshness')]
#[Description('Raise a critical alert when the catalogue has not synced successfully for too long')]
class CheckSyncFreshness extends Command
{
    public function handle(AlertService $alerts, ErpFeed $feed): int
    {
        $source = $feed->source();
        $maxAge = (int) config('el3ktron.sync.max_age_minutes');
        $last = SyncRun::where('source', $source)->where('status', 'succeeded')->max('finished_at');
        $key = "sync_stale:{$source}";

        if ($last !== null && now()->diffInMinutes($last, absolute: true) <= $maxAge) {
            $alerts->resolve($key);
            $this->info("Fresh: last successful sync {$last}.");

            return self::SUCCESS;
        }

        $alerts->raise(
            'sync_stale',
            AlertSeverity::Critical,
            $key,
            $last === null
                ? "El catálogo ({$source}) no se ha sincronizado nunca con éxito."
                : "El catálogo ({$source}) lleva más de {$maxAge} min sin sincronizarse con éxito (último: {$last}).",
            ['last_success' => $last, 'max_age_minutes' => $maxAge],
        );
        $this->error('Stale: alert raised.');

        return self::FAILURE;
    }
}
