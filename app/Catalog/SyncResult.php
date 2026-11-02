<?php

namespace App\Catalog;

final class SyncResult
{
    public int $seen = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    /** @var list<array{row: int, reason: string}> */
    public array $invalid = [];

    /** @var list<array{sku: string, change: string, from: string, to: string, installations: int}> */
    public array $changes = [];
}
