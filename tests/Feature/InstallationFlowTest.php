<?php

use App\Models\Family;
use App\Models\Installation;
use App\Models\Stock;

beforeEach(fn () => actingAsApiUser());

it('takes an installation from draft to picking list, substituting what is short', function () {
    $family = Family::factory()->create();
    $ok = productWithStock('E3-00001', 50, $family, aisle: 3, bay: 4);
    $short = productWithStock('E3-00002', 1, $family, price: 1000, aisle: 1, bay: 2);
    $substitute = productWithStock('E3-00003', 40, $family, price: 1100, aisle: 2, bay: 18);
    store('FD-01');
    planogramWith([$ok->id => 5, $short->id => 4], 'EXP-01');

    $id = $this->postJson('/api/v1/installations', ['store' => 'FD-01', 'planogram' => 'EXP-01'])
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/installations/{$id}/check")
        ->assertOk()
        ->assertJsonPath('report.1.status', 'substituted')
        ->assertJsonPath('report.1.substitute_sku', 'E3-00003')
        ->assertJsonPath('installation.needs_review', true);

    // Substitutes need a person's confirmation before stock is held.
    $this->postJson("/api/v1/installations/{$id}/reserve")->assertStatus(409);
    $this->postJson("/api/v1/installations/{$id}/confirm-review")->assertOk();
    $this->postJson("/api/v1/installations/{$id}/reserve")->assertOk()->assertJsonPath('data.status', 'reserved');

    expect(Stock::where('product_id', $substitute->id)->value('reserved'))->toBe(4)
        ->and(Stock::where('product_id', $short->id)->value('reserved'))->toBe(0);

    $list = $this->getJson("/api/v1/installations/{$id}/picking-list")->assertOk();
    expect($list->json('lines'))->toHaveCount(2)
        ->and($list->json('route_meters'))->toBeLessThanOrEqual($list->json('catalogue_order_meters'));
});

it('reports a line as missing when there is no substitute', function () {
    $lonely = productWithStock('E3-00009', 0);
    store('FD-01');
    planogramWith([$lonely->id => 2], 'EXP-01');
    $id = $this->postJson('/api/v1/installations', ['store' => 'FD-01', 'planogram' => 'EXP-01'])->json('data.id');

    $this->postJson("/api/v1/installations/{$id}/check")
        ->assertJsonPath('report.0.status', 'missing')
        ->assertJsonPath('installation.review_reason', '1 referencias sin stock ni sustituta');
});

it('reserves all lines or none when stock disappears after the check', function () {
    $a = productWithStock('E3-00001', 10);
    $b = productWithStock('E3-00002', 10);
    store('FD-01');
    planogramWith([$a->id => 5, $b->id => 5], 'EXP-01');
    $id = $this->postJson('/api/v1/installations', ['store' => 'FD-01', 'planogram' => 'EXP-01'])->json('data.id');
    $this->postJson("/api/v1/installations/{$id}/check")->assertOk();

    Stock::where('product_id', $b->id)->update(['reserved' => 8]); // someone else took it meanwhile

    $this->postJson("/api/v1/installations/{$id}/reserve")->assertStatus(409);
    expect(Stock::where('product_id', $a->id)->value('reserved'))->toBe(0)
        ->and(Installation::find($id)->status->value)->toBe('checked');
});

it('refuses the picking list before reserving', function () {
    store('FD-01');
    planogramWith([], 'EXP-01');
    $id = $this->postJson('/api/v1/installations', ['store' => 'FD-01', 'planogram' => 'EXP-01'])->json('data.id');

    $this->getJson("/api/v1/installations/{$id}/picking-list")->assertStatus(409);
});

it('validates the installation payload', function () {
    $this->postJson('/api/v1/installations', ['store' => 'NOPE'])->assertUnprocessable()
        ->assertJsonValidationErrors(['store', 'planogram']);
});
