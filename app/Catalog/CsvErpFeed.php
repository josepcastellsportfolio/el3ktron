<?php

namespace App\Catalog;

use RuntimeException;

/** The ERP's nightly catalogue export: a CSV with a header row. */
final class CsvErpFeed implements ErpFeed
{
    private const COLUMNS = ['sku', 'ean', 'name', 'family_code', 'price_cents', 'status'];

    public function __construct(private readonly string $path) {}

    public function source(): string
    {
        return 'erp-csv';
    }

    public function rows(): iterable
    {
        $handle = @fopen($this->path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Catalogue export not readable: {$this->path}");
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if ($header === false || array_diff(self::COLUMNS, array_map('trim', $header)) !== []) {
                throw new RuntimeException('Catalogue export has an unexpected header: '.implode(',', $header ?: []));
            }
            $header = array_map('trim', $header);
            $number = 0;
            while (($fields = fgetcsv($handle, escape: '')) !== false) {
                $number++;
                if ($fields === [null]) {
                    continue; // blank line
                }
                yield $number => array_combine($header, array_pad(array_map('trim', $fields), count($header), ''));
            }
        } finally {
            fclose($handle);
        }
    }
}
