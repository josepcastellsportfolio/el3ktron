<?php

use App\Console\Commands\EvalExtractor;
use App\Orders\ExtractedLine;
use App\Orders\RuleBasedExtractor;

$extractor = new RuleBasedExtractor('/\bE3-\d{5}\b/');
$fixture = fn (string $name) => [
    file_get_contents(__DIR__."/../Fixtures/orders/{$name}.txt"),
    json_decode(file_get_contents(__DIR__."/../Fixtures/orders/{$name}.json"), true),
];

it('reads every line of the structured orders', function (string $name) use ($extractor, $fixture) {
    [$text, $expected] = $fixture($name);
    $lines = $extractor->extract($text);

    expect(EvalExtractor::matches($expected, $lines))->toBe(count($expected))
        ->and($lines)->toHaveCount(count($expected));
})->with(['01-tabla', '02-csv', '03-unidades', '04-ean', '07-firma-con-numeros', '08-tabulado']);

it('never takes digits of a SKU, an EAN or a postcode as the quantity', function () use ($extractor) {
    $lines = $extractor->extract("E3-00123\n8412345000017\n43580 Deltebre");

    expect($lines)->toBe([]);
});

it('cannot read free text: that is what the LLM extractor is for', function () use ($extractor, $fixture) {
    [$text] = $fixture('05-texto-libre');

    expect($extractor->extract($text))->toBe([]);
});

it('scores a match only with the same product and quantity', function () {
    $expected = [['sku' => 'E3-00001', 'quantity' => 2], ['text' => 'bridas', 'quantity' => 3]];
    $got = [new ExtractedLine('E3-00001 x 2', 2, 'E3-00001'), new ExtractedLine('3 cajas de bridas', 3, description: 'bridas negras'), new ExtractedLine('?', 9, 'E3-00009')];

    expect(EvalExtractor::matches($expected, $got))->toBe(2);
});
