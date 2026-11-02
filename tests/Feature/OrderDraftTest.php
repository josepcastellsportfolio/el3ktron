<?php

use App\Models\OrderDraft;
use App\Orders\LlmExtractor;
use App\Orders\OrderExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => actingAsApiUser());

it('turns a written order into a pending draft matched to products', function () {
    productWithStock('E3-00012', 10);
    productWithStock('E3-00045', 10);

    $this->postJson('/api/v1/order-drafts', ['text' => file_get_contents(base_path('tests/Fixtures/orders/01-tabla.txt'))])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.extractor', 'rules')
        ->assertJsonCount(3, 'data.lines')
        ->assertJsonPath('data.unmatched_lines', 1); // E3-00101 is not in this catalogue
});

it('will not approve a draft with unmatched lines until a person corrects them', function () {
    productWithStock('E3-00012', 10);
    productWithStock('E3-00045', 10);
    $id = $this->postJson('/api/v1/order-drafts', ['text' => "E3-00012 x 3\nE3-00999 x 1"])->json('data.id');

    $this->postJson("/api/v1/order-drafts/{$id}/approve")->assertUnprocessable()->assertJsonValidationErrors('lines');

    $this->putJson("/api/v1/order-drafts/{$id}", ['lines' => [['sku' => 'E3-00012', 'quantity' => 3], ['sku' => 'E3-00045', 'quantity' => 1]]])
        ->assertOk()->assertJsonPath('data.unmatched_lines', 0);
    $this->postJson("/api/v1/order-drafts/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

    expect(OrderDraft::find($id)->reviewed_by)->not->toBeNull();
    $this->postJson("/api/v1/order-drafts/{$id}/reject")->assertUnprocessable();
});

it('reads free text with the LLM extractor and keeps only valid lines', function () {
    app()->bind(OrderExtractor::class, fn () => new LlmExtractor('test-key', 'claude-haiku-5-5', 'https://api.anthropic.test', 5));
    Http::fake(['api.anthropic.test/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['lines' => [
        ['sku' => null, 'ean' => null, 'description' => 'bridas negras grandes', 'quantity' => 2, 'raw' => 'dos cajas de las bridas negras grandes'],
        ['sku' => null, 'ean' => null, 'description' => 'regletas', 'quantity' => 0, 'raw' => 'regletas'],
    ]])]]])]);

    $this->postJson('/api/v1/order-drafts', ['text' => 'mándame dos cajas de las bridas negras grandes'])
        ->assertCreated()
        ->assertJsonPath('data.extractor', 'llm')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.unmatched_lines', 1);

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-key'));
});
