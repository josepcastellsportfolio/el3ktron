<?php

namespace App\Jobs;

use App\Alerts\AlertService;
use App\Catalog\CatalogSync;
use App\Catalog\ErpFeed;
use App\Enums\AlertSeverity;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs CatalogSync on the queue (RabbitMQ in docker-compose). Retries with
 * backoff for transient failures; when it gives up, `failed()` raises a
 * critical alert so the failure is never silent. Unique: two syncs never
 * overlap.
 */
class SyncCatalog implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public int $uniqueFor = 3600;

    public function handle(CatalogSync $sync, ErpFeed $feed): void
    {
        $sync->run($feed);
        app(AlertService::class)->resolve('sync_failed:'.$feed->source());
    }

    public function failed(?Throwable $exception): void
    {
        $feed = app(ErpFeed::class);
        app(AlertService::class)->raise(
            'sync_failed',
            AlertSeverity::Critical,
            'sync_failed:'.$feed->source(),
            "La sincronización del catálogo ({$feed->source()}) ha fallado {$this->tries} veces: ".($exception?->getMessage() ?? 'sin detalle'),
        );
    }
}
