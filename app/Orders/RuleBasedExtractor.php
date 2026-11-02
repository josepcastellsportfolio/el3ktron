<?php

namespace App\Orders;

/**
 * The baseline, no AI: one order line per text line that has a SKU or an EAN
 * and a quantity. Precise on structured orders, blind to free text
 * («mándame dos cajas de las bridas negras»). The eval command measures both
 * extractors on the same fixtures.
 */
final class RuleBasedExtractor implements OrderExtractor
{
    private const QUANTITY_PATTERNS = [
        '/(?:^|\s)[x×]\s*(\d{1,5})\b/iu',                                 // x 12
        '/\b(\d{1,5})\s*(?:uds?\.?|unidades|u\.|pcs|piezas|cajas?)\b/iu', // 12 uds
        '/(?:cantidad|cant\.?|qty)\s*[:=]?\s*(\d{1,5})\b/iu',             // cantidad: 12
        '/^\s*(\d{1,5})\s+/u',                                            // 12 E3-00001
        '/[;,|\t]\s*(\d{1,5})\s*$/u',                                     // E3-00001; 12
    ];

    public function __construct(private readonly string $skuPattern) {}

    public function name(): string
    {
        return 'rules';
    }

    public function extract(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = $this->parse(trim($raw));
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function parse(string $raw): ?ExtractedLine
    {
        $sku = preg_match($this->skuPattern, $raw, $m) ? $m[0] : null;
        $ean = preg_match('/\b\d{13}\b/', $raw, $m) ? $m[0] : null;
        if ($sku === null && $ean === null) {
            return null;
        }

        // Quantities are looked for with the identifiers removed, so digits of a SKU or EAN never count.
        $rest = trim(str_replace(array_filter([$sku, $ean]), ' ', $raw));
        foreach (self::QUANTITY_PATTERNS as $pattern) {
            if (preg_match($pattern, $rest, $m) && (int) $m[1] > 0) {
                return new ExtractedLine($raw, (int) $m[1], $sku, $ean);
            }
        }

        return null;
    }
}
