<?php

namespace App\Catalog;

/**
 * Where the catalogue comes from. Today a CSV export; tomorrow an ERP API or
 * a queue consumer, without touching CatalogSync.
 */
interface ErpFeed
{
    /** Short name stored on each SyncRun and used in alert keys. */
    public function source(): string;

    /** @return iterable<int, array<string, string>> rows keyed by column name, numbered from 1 */
    public function rows(): iterable;
}
