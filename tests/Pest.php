<?php

use App\Models\Family;
use App\Models\Location;
use App\Models\Planogram;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/** Authenticates the test as an API user. */
function actingAsApiUser(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

/** A product with `quantity` units at a location in the given aisle and bay. */
function productWithStock(string $sku, int $quantity, ?Family $family = null, int $price = 1000, int $aisle = 1, int $bay = 1): Product
{
    $product = Product::factory()->create(['sku' => $sku, 'family_id' => ($family ?? Family::factory()->create())->id, 'price_cents' => $price]);
    $location = Location::factory()->create(['aisle' => $aisle, 'bay' => $bay]);
    Stock::create(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => $quantity]);

    return $product;
}

/** @param  array<int, int>  $items  product id => quantity */
function planogramWith(array $items, string $code = 'EXP-TEST'): Planogram
{
    $planogram = Planogram::create(['code' => $code, 'name' => 'Test']);
    foreach ($items as $productId => $quantity) {
        $planogram->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
    }

    return $planogram;
}

function store(string $code = 'FD-01'): Store
{
    return Store::factory()->create(['code' => $code]);
}

/** Writes a catalogue CSV and returns its path. @param list<array<int, string|int>> $rows */
function catalogueCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'catalog').'.csv';
    $out = fopen($path, 'w');
    fputcsv($out, ['sku', 'ean', 'name', 'family_code', 'price_cents', 'status'], escape: '');
    foreach ($rows as $row) {
        fputcsv($out, $row, escape: '');
    }
    fclose($out);

    return $path;
}
