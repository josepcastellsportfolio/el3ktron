<?php

namespace App\Installations;

use App\Enums\InstallationStatus;
use App\Models\Installation;
use App\Models\InstallationLine;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

/**
 * Holds the stock for a checked installation, all or nothing.
 *
 * Rows are locked (SELECT … FOR UPDATE) so two installations reserving the
 * same product at once cannot both take the last units. Each line takes from
 * the locations with the most free stock first, which keeps the number of
 * stops on the picking list low.
 */
final class StockReserver
{
    public function reserve(Installation $installation): void
    {
        if ($installation->status !== InstallationStatus::Checked) {
            throw new InstallationException("Installation {$installation->id} is {$installation->status->value}; check it before reserving.");
        }
        if ($installation->needs_review) {
            throw new InstallationException("Installation {$installation->id} needs review ({$installation->review_reason}); confirm it before reserving.");
        }

        DB::transaction(function () use ($installation) {
            foreach ($installation->lines()->with('product')->get() as $line) {
                $this->reserveLine($line);
            }
            $installation->update(['status' => InstallationStatus::Reserved, 'reserved_at' => now()]);
        });
    }

    private function reserveLine(InstallationLine $line): void
    {
        $rows = Stock::query()
            ->where('product_id', $line->product_id)
            ->whereColumn('quantity', '>', 'reserved')
            ->lockForUpdate()
            ->get()
            ->sortByDesc(fn (Stock $stock) => $stock->available());

        $left = $line->quantity;
        foreach ($rows as $stock) {
            $take = min($left, $stock->available());
            $stock->increment('reserved', $take);
            $line->reservations()->create(['stock_id' => $stock->id, 'quantity' => $take]);
            $left -= $take;
            if ($left === 0) {
                return;
            }
        }

        throw new InstallationException("Not enough free stock for {$line->product->sku}: {$left} units short. Check the installation again.");
    }
}
