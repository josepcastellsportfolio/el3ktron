<?php

use App\Catalog\CatalogSync;
use App\Catalog\CsvErpFeed;
use App\Catalog\ErpFeed;
use App\Jobs\SyncCatalog;
use App\Models\Alert;
use App\Models\Installation;
use App\Models\Product;
use App\Models\SyncRun;

$sync = fn (string $csv) => app(CatalogSync::class)->run(new CsvErpFeed($csv));

it('creates products and families from the ERP export', function () use ($sync) {
    $result = $sync(catalogueCsv([
        ['E3-00001', '8412345000017', 'Brida negra', 'BRIDAS', 120, 'active'],
        ['E3-00002', '', 'Cinta azul', 'CINTAS', 95, 'active'],
    ]));

    expect($result->created)->toBe(2)
        ->and(Product::where('sku', 'E3-00001')->first()->family->code)->toBe('BRIDAS')
        ->and(SyncRun::sole()->status)->toBe('succeeded');
});

it('is idempotent: the same export twice writes nothing the second time', function () use ($sync) {
    $csv = catalogueCsv([['E3-00001', '', 'Brida negra', 'BRIDAS', 120, 'active']]);
    $sync($csv);
    $updatedAt = Product::sole()->updated_at;

    $this->travel(5)->minutes();
    $second = $sync($csv);

    expect($second->unchanged)->toBe(1)
        ->and($second->updated + $second->created)->toBe(0)
        ->and(Product::sole()->updated_at->eq($updatedAt))->toBeTrue();
});

it('skips bad rows, applies the rest and raises one warning', function () use ($sync) {
    $result = $sync(catalogueCsv([
        ['E3-00001', '', 'Brida negra', 'BRIDAS', 120, 'active'],
        ['E3-00002', '', 'Sin precio', 'BRIDAS', 'doce', 'active'],
        ['', '', 'Sin sku', 'BRIDAS', 100, 'active'],
        ['E3-00004', '123', 'EAN corto', 'BRIDAS', 100, 'active'],
    ]));

    expect($result->created)->toBe(1)
        ->and($result->invalid)->toHaveCount(3)
        ->and(Alert::open()->where('type', 'sync_invalid_rows')->count())->toBe(1);
});

it('flags active installations and alerts when a product is discontinued or reprized', function () use ($sync) {
    $sync(catalogueCsv([['E3-00001', '', 'Brida negra', 'BRIDAS', 120, 'active'], ['E3-00002', '', 'Cinta', 'CINTAS', 95, 'active']]));
    $product = Product::where('sku', 'E3-00001')->first();
    $installation = Installation::create(['store_id' => store()->id, 'planogram_id' => planogramWith([$product->id => 4])->id]);

    $result = $sync(catalogueCsv([['E3-00001', '', 'Brida negra', 'BRIDAS', 150, 'discontinued'], ['E3-00002', '', 'Cinta', 'CINTAS', 95, 'active']]));

    expect($result->changes)->toHaveCount(2)
        ->and($installation->refresh()->needs_review)->toBeTrue()
        ->and(Alert::open()->where('type', 'catalog_change')->count())->toBe(2)
        ->and(Alert::where('dedupe_key', 'catalog_change:E3-00001:price')->sole()->context['installations'])->toBe(1);
});

it('records a failed run and rethrows when the export cannot be read', function () use ($sync) {
    expect(fn () => $sync('/nonexistent/catalog.csv'))->toThrow(RuntimeException::class);
    expect(SyncRun::sole()->status)->toBe('failed');
});

it('raises a critical alert when the queued job gives up', function () {
    app()->bind(ErpFeed::class, fn () => new CsvErpFeed('/nonexistent/catalog.csv'));

    (new SyncCatalog)->failed(new RuntimeException('boom'));

    expect(Alert::open()->where('type', 'sync_failed')->sole()->severity->value)->toBe('critical');
});
