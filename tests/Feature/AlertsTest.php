<?php

use App\Alerts\AlertService;
use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Mail;

it('raises an alert once while it stays open, and mails it once', function () {
    config(['el3ktron.alerts.mail_to' => 'ops@example.test']);
    Mail::fake();
    $alerts = app(AlertService::class);

    $alerts->raise('sync_failed', AlertSeverity::Critical, 'sync_failed:erp-csv', 'falla');
    $alerts->raise('sync_failed', AlertSeverity::Critical, 'sync_failed:erp-csv', 'falla otra vez');

    expect(Alert::count())->toBe(1);
    Mail::assertSentCount(1);
});

it('raises a new alert after the previous one was resolved', function () {
    $alerts = app(AlertService::class);
    $alerts->raise('x', AlertSeverity::Warning, 'k', 'm');
    $alerts->resolve('k');
    $alerts->raise('x', AlertSeverity::Warning, 'k', 'm');

    expect(Alert::count())->toBe(2)->and(Alert::open()->count())->toBe(1);
});

it('alerts when the catalogue never synced, and resolves once a sync succeeds', function () {
    $this->artisan('el3ktron:check-sync-freshness')->assertFailed();
    expect(Alert::open()->where('type', 'sync_stale')->count())->toBe(1);

    SyncRun::create(['source' => 'erp-csv', 'status' => 'succeeded', 'started_at' => now(), 'finished_at' => now()]);
    $this->artisan('el3ktron:check-sync-freshness')->assertSuccessful();

    expect(Alert::open()->where('type', 'sync_stale')->count())->toBe(0);
});

it('alerts when the last good sync is too old', function () {
    SyncRun::create(['source' => 'erp-csv', 'status' => 'succeeded', 'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3)]);
    SyncRun::create(['source' => 'erp-csv', 'status' => 'failed', 'started_at' => now(), 'finished_at' => now()]);

    $this->artisan('el3ktron:check-sync-freshness')->assertFailed();
});

it('lists open alerts over the API and resolves them', function () {
    actingAsApiUser();
    $alert = app(AlertService::class)->raise('x', AlertSeverity::Warning, 'k', 'm');

    $this->getJson('/api/v1/alerts')->assertOk()->assertJsonPath('data.0.id', $alert->id);
    $this->postJson("/api/v1/alerts/{$alert->id}/resolve")->assertOk();
    $this->getJson('/api/v1/alerts')->assertOk()->assertJsonCount(0, 'data');
});
