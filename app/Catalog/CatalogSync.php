<?php

namespace App\Catalog;

use App\Alerts\AlertService;
use App\Enums\AlertSeverity;
use App\Enums\InstallationStatus;
use App\Enums\ProductStatus;
use App\Models\Family;
use App\Models\Installation;
use App\Models\Product;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mirrors the ERP catalogue into `products`, and makes the changes that
 * matter visible instead of letting them land silently.
 *
 * - Idempotent: each row is hashed; an unchanged row is not written, so
 *   running the same export twice changes nothing.
 * - A bad row is skipped and reported, it does not stop the other 27,999.
 * - A price change or a discontinuation flags every active installation that
 *   uses the product and raises one alert per product.
 * - Every run leaves a SyncRun, which the freshness check reads.
 */
final class CatalogSync
{
    public function __construct(private readonly AlertService $alerts) {}

    public function run(ErpFeed $feed): SyncResult
    {
        $run = SyncRun::create(['source' => $feed->source(), 'status' => 'running', 'started_at' => now()]);
        $result = new SyncResult;

        try {
            foreach ($feed->rows() as $number => $row) {
                $result->seen++;
                $this->applyRow($number, $row, $result);
            }
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'error' => $e->getMessage(), 'rows_seen' => $result->seen, 'finished_at' => now()]);
            throw $e;
        }

        $run->update([
            'status' => 'succeeded',
            'rows_seen' => $result->seen,
            'rows_changed' => $result->created + $result->updated,
            'finished_at' => now(),
        ]);

        $this->report($feed, $result);

        return $result;
    }

    /** @param  array<string, string>  $row */
    private function applyRow(int $number, array $row, SyncResult $result): void
    {
        $invalid = $this->validate($row);
        if ($invalid !== null) {
            $result->invalid[] = ['row' => $number, 'reason' => $invalid];

            return;
        }

        $hash = sha1(implode('|', [$row['sku'], $row['ean'], $row['name'], $row['family_code'], $row['price_cents'], $row['status']]));
        $product = Product::where('sku', $row['sku'])->first();
        if ($product?->erp_hash === $hash) {
            $result->unchanged++;

            return;
        }

        DB::transaction(function () use ($row, $hash, $product, $result) {
            $family = Family::firstOrCreate(['code' => $row['family_code']], ['name' => $row['family_code']]);
            $attributes = [
                'ean' => $row['ean'] !== '' ? $row['ean'] : null,
                'name' => $row['name'],
                'family_id' => $family->id,
                'price_cents' => (int) $row['price_cents'],
                'status' => ProductStatus::from($row['status']),
                'erp_hash' => $hash,
                'synced_at' => now(),
            ];

            if ($product === null) {
                Product::create(['sku' => $row['sku'], ...$attributes]);
                $result->created++;

                return;
            }

            $this->recordChanges($product, $attributes, $result);
            $product->update($attributes);
            $result->updated++;
        });
    }

    /** @param  array<string, string>  $row */
    private function validate(array $row): ?string
    {
        return match (true) {
            $row['sku'] === '' => 'empty sku',
            $row['name'] === '' => 'empty name',
            $row['family_code'] === '' => 'empty family_code',
            ! ctype_digit($row['price_cents']) => "price_cents is not a whole number: '{$row['price_cents']}'",
            ProductStatus::tryFrom($row['status']) === null => "unknown status '{$row['status']}'",
            $row['ean'] !== '' && ! preg_match('/^\d{13}$/', $row['ean']) => "ean is not 13 digits: '{$row['ean']}'",
            default => null,
        };
    }

    /** @param  array<string, mixed>  $new */
    private function recordChanges(Product $product, array $new, SyncResult $result): void
    {
        if ($product->price_cents !== $new['price_cents']) {
            $flagged = $this->flagInstallations($product, "Precio de {$product->sku} cambiado en el ERP");
            $result->changes[] = ['sku' => $product->sku, 'change' => 'price', 'from' => (string) $product->price_cents, 'to' => (string) $new['price_cents'], 'installations' => $flagged];
        }
        if ($product->isActive() && $new['status'] === ProductStatus::Discontinued) {
            $flagged = $this->flagInstallations($product, "{$product->sku} descatalogado en el ERP");
            $result->changes[] = ['sku' => $product->sku, 'change' => 'discontinued', 'from' => 'active', 'to' => 'discontinued', 'installations' => $flagged];
        }
    }

    /** Active installations whose planogram or lines use the product need a person to look again. */
    private function flagInstallations(Product $product, string $reason): int
    {
        return Installation::query()
            ->whereIn('status', InstallationStatus::active())
            ->where(fn ($q) => $q
                ->whereHas('planogram.items', fn ($items) => $items->where('product_id', $product->id))
                ->orWhereHas('lines', fn ($lines) => $lines->where('product_id', $product->id)))
            ->update(['needs_review' => true, 'review_reason' => $reason]);
    }

    private function report(ErpFeed $feed, SyncResult $result): void
    {
        foreach ($result->changes as $change) {
            $this->alerts->raise(
                'catalog_change',
                AlertSeverity::Warning,
                "catalog_change:{$change['sku']}:{$change['change']}",
                "{$change['sku']}: {$change['change']} ({$change['from']} → {$change['to']}). Implantaciones activas marcadas para revisar: {$change['installations']}.",
                $change,
            );
        }

        if ($result->invalid !== []) {
            $this->alerts->raise(
                'sync_invalid_rows',
                AlertSeverity::Warning,
                "sync_invalid_rows:{$feed->source()}",
                count($result->invalid)." filas del ERP rechazadas en la última sincronización ({$feed->source()}).",
                ['rows' => array_slice($result->invalid, 0, 50)],
            );
        } else {
            $this->alerts->resolve("sync_invalid_rows:{$feed->source()}");
        }
    }
}
