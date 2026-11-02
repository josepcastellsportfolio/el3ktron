<?php

namespace App\Installations;

use App\Enums\InstallationStatus;
use App\Enums\ProductStatus;
use App\Models\Installation;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

/**
 * Before an installation gets a date, check that every product of its
 * planogram can actually go: active and with enough free stock. For the ones
 * that cannot, propose a substitute (SubstitutePicker). What has neither is
 * reported as missing and the installation is flagged for review, instead of
 * finding out on the warehouse floor and sending a second van later.
 *
 * Checking again replaces the previous result; a reserved installation
 * cannot be checked again.
 */
final class AvailabilityChecker
{
    /** @return list<array{product_id: int, sku: string, quantity: int, status: string, substitute_id: int|null, substitute_sku: string|null}> */
    public function check(Installation $installation): array
    {
        if (! in_array($installation->status, [InstallationStatus::Draft, InstallationStatus::Checked], true)) {
            throw new InstallationException("Installation {$installation->id} is {$installation->status->value}; only draft or checked installations can be checked.");
        }

        $items = $installation->planogram->items()->with('product')->get();
        $available = $this->availableByProduct($items->pluck('product_id')->all());
        $inPlanogram = array_fill_keys($items->pluck('product_id')->all(), true);
        $report = [];

        foreach ($items as $item) {
            $product = $item->product;
            $free = $available[$product->id] ?? 0;

            if ($product->isActive() && $free >= $item->quantity) {
                $available[$product->id] = $free - $item->quantity;
                $report[] = $this->line($product, $item->quantity, 'ok');

                continue;
            }

            $substitute = $this->substituteFor($product, $item->quantity, $available, $inPlanogram);
            if ($substitute === null) {
                $report[] = $this->line($product, $item->quantity, 'missing');

                continue;
            }
            $available[$substitute->id] = ($available[$substitute->id] ?? 0) - $item->quantity;
            $inPlanogram[$substitute->id] = true;
            $report[] = $this->line($product, $item->quantity, 'substituted', $substitute);
        }

        $this->apply($installation, $report);

        return $report;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    private function availableByProduct(array $productIds): array
    {
        return Stock::query()
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity - reserved) AS free'))
            ->pluck('free', 'product_id')
            ->map(fn ($free) => (int) $free)
            ->all();
    }

    /**
     * @param  array<int, int>  $available  updated as substitutes are taken, so two lines never count the same units
     * @param  array<int, true>  $exclude
     */
    private function substituteFor(Product $product, int $quantity, array &$available, array $exclude): ?Product
    {
        $family = Product::query()
            ->where('family_id', $product->family_id)
            ->where('status', ProductStatus::Active)
            ->get();

        $missing = array_diff($family->pluck('id')->all(), array_keys($available));
        if ($missing !== []) {
            $available += $this->availableByProduct(array_values($missing));
        }

        $wanted = new Candidate($product->id, $product->family_id, $product->price_cents, 0, false);
        $candidates = $family->map(fn (Product $p) => new Candidate($p->id, $p->family_id, $p->price_cents, $available[$p->id] ?? 0, true));
        $pick = SubstitutePicker::pick($wanted, $quantity, $candidates, $exclude);

        return $pick ? $family->firstWhere('id', $pick->productId) : null;
    }

    /** @return array{product_id: int, sku: string, quantity: int, status: string, substitute_id: int|null, substitute_sku: string|null} */
    private function line(Product $product, int $quantity, string $status, ?Product $substitute = null): array
    {
        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'status' => $status,
            'substitute_id' => $substitute?->id,
            'substitute_sku' => $substitute?->sku,
        ];
    }

    /** @param  list<array{product_id: int, sku: string, quantity: int, status: string, substitute_id: int|null, substitute_sku: string|null}>  $report */
    private function apply(Installation $installation, array $report): void
    {
        $missing = count(array_filter($report, fn ($line) => $line['status'] === 'missing'));
        $substituted = count(array_filter($report, fn ($line) => $line['status'] === 'substituted'));

        DB::transaction(function () use ($installation, $report, $missing, $substituted) {
            $installation->lines()->delete();
            foreach ($report as $line) {
                if ($line['status'] === 'missing') {
                    continue;
                }
                $installation->lines()->create([
                    'product_id' => $line['substitute_id'] ?? $line['product_id'],
                    'substitute_for_product_id' => $line['substitute_id'] ? $line['product_id'] : null,
                    'quantity' => $line['quantity'],
                ]);
            }

            $reasons = array_filter([
                $missing ? "{$missing} referencias sin stock ni sustituta" : null,
                $substituted ? "{$substituted} sustitutas propuestas" : null,
            ]);
            $installation->update([
                'status' => InstallationStatus::Checked,
                'checked_at' => now(),
                'needs_review' => $reasons !== [],
                'review_reason' => $reasons ? implode('; ', $reasons) : null,
            ]);
        });
    }
}
