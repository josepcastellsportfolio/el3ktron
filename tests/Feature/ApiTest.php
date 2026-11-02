<?php

use App\Jobs\SyncCatalog;
use App\Models\Family;
use App\Models\Stock;
use Illuminate\Support\Facades\Queue;

it('rejects requests without a token', function () {
    $this->getJson('/api/v1/products')->assertUnauthorized();
});

it('answers the health check without a token', function () {
    $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
});

it('lists products with their free stock, filtered and paginated', function () {
    actingAsApiUser();
    $family = Family::factory()->create(['code' => 'BRIDAS']);
    $product = productWithStock('E3-00001', 30, $family);
    Stock::where('product_id', $product->id)->update(['reserved' => 12]);
    productWithStock('E3-00002', 5);

    $this->getJson('/api/v1/products?family=BRIDAS')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sku', 'E3-00001')
        ->assertJsonPath('data.0.available', 18)
        ->assertJsonPath('meta.total', 1);

    $this->getJson('/api/v1/products?q=E3-0000&per_page=1')->assertOk()->assertJsonPath('meta.last_page', 2);
});

it('returns 404 for an unknown SKU', function () {
    actingAsApiUser();
    $this->getJson('/api/v1/products/E3-99999')->assertNotFound();
});

it('queues a catalogue sync', function () {
    actingAsApiUser();
    Queue::fake();

    $this->postJson('/api/v1/sync/catalog')->assertAccepted();
    Queue::assertPushed(SyncCatalog::class);
});
