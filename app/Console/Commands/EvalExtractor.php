<?php

namespace App\Console\Commands;

use App\Orders\ExtractedLine;
use App\Providers\AppServiceProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scores an order extractor against labelled orders (tests/Fixtures/orders):
 * an extracted line is right when its quantity matches and it names the same
 * product (same SKU, same EAN, or the expected word in its text).
 *
 * The number to watch is recall: a missed line is a product the store asked
 * for and does not get. Precision matters too, but a wrong extra line is
 * caught by the person who approves the draft.
 */
#[Signature('el3ktron:eval-extractor {--extractor=rules : rules | llm}')]
#[Description('Precision and recall of an order extractor on the labelled fixtures')]
class EvalExtractor extends Command
{
    public function handle(): int
    {
        $extractor = AppServiceProvider::extractor((string) $this->option('extractor'));
        $rows = [];
        $totals = ['expected' => 0, 'extracted' => 0, 'right' => 0];

        foreach (glob(base_path('tests/Fixtures/orders/*.txt')) ?: [] as $file) {
            $expected = json_decode((string) file_get_contents(substr($file, 0, -4).'.json'), true);
            $extracted = $extractor->extract((string) file_get_contents($file));
            $right = self::matches($expected, $extracted);

            $rows[] = [basename($file, '.txt'), count($expected), count($extracted), $right];
            $totals['expected'] += count($expected);
            $totals['extracted'] += count($extracted);
            $totals['right'] += $right;
        }

        $this->table(['Order', 'Expected lines', 'Extracted', 'Right'], $rows);
        $precision = $totals['extracted'] ? $totals['right'] / $totals['extracted'] : 0;
        $recall = $totals['expected'] ? $totals['right'] / $totals['expected'] : 0;
        $this->line(sprintf('%s: precision %.1f %%, recall %.1f %% (%d of %d lines)',
            $extractor->name(), $precision * 100, $recall * 100, $totals['right'], $totals['expected']));

        return self::SUCCESS;
    }

    /**
     * Lines right, each expected line matched at most once.
     *
     * @param  list<array{sku?: string, ean?: string, text?: string, quantity: int}>  $expected
     * @param  list<ExtractedLine>  $extracted
     */
    public static function matches(array $expected, array $extracted): int
    {
        $right = 0;
        foreach ($expected as $want) {
            foreach ($extracted as $i => $got) {
                if ($got->quantity === $want['quantity'] && self::sameProduct($want, $got)) {
                    $right++;
                    unset($extracted[$i]);

                    break;
                }
            }
        }

        return $right;
    }

    /** @param  array{sku?: string, ean?: string, text?: string, quantity: int}  $want */
    private static function sameProduct(array $want, ExtractedLine $got): bool
    {
        return match (true) {
            isset($want['sku']) => $got->sku === $want['sku'],
            isset($want['ean']) => $got->ean === $want['ean'],
            default => str_contains(mb_strtolower($got->description.' '.$got->raw), mb_strtolower($want['text'])),
        };
    }
}
