<?php

namespace Database\Seeders;

use App\Catalog\CatalogSync;
use App\Catalog\CsvErpFeed;
use App\Models\Location;
use App\Models\Planogram;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A small, deterministic demo warehouse. The catalogue goes in the way it
 * would in production: an ERP export (storage/app/erp/catalog.csv) read by
 * CatalogSync. Prints an API token for the demo user.
 */
class DemoSeeder extends Seeder
{
    private const FAMILIES = ['BRIDAS', 'CINTAS', 'REGLETAS', 'DESTORNILLADORES', 'BROCAS', 'TACOS', 'BOMBILLAS', 'CABLE', 'ENCHUFES', 'HERRAMIENTA'];

    public function run(CatalogSync $sync): void
    {
        $random = new Randomizer(new Mt19937(42));

        $csv = (string) config('el3ktron.sync.catalog_csv');
        $this->writeCatalogue($csv, $random);
        $sync->run(new CsvErpFeed($csv));

        $locations = $this->locations();
        foreach (Product::all() as $product) {
            foreach ($random->pickArrayKeys($locations, $random->getInt(1, 2)) as $i) {
                Stock::create(['product_id' => $product->id, 'location_id' => $locations[$i], 'quantity' => $random->getInt(0, 1) ? $random->getInt(20, 300) : $random->getInt(0, 6)]);
            }
        }

        foreach (range(1, 10) as $n) {
            Store::create(['code' => sprintf('FD-%02d', $n), 'name' => "Ferretería demo {$n}", 'city' => 'Tarragona']);
        }

        $productIds = Product::pluck('id')->all();
        foreach (['EXP-ELEC-120' => 'Expositor electricidad 120 cm', 'EXP-FIJ-60' => 'Expositor fijación 60 cm', 'LIN-HERR-200' => 'Lineal herramienta 200 cm'] as $code => $name) {
            $planogram = Planogram::create(['code' => $code, 'name' => $name]);
            foreach ($random->pickArrayKeys(array_flip($productIds), $random->getInt(40, 80)) as $productId) {
                $planogram->items()->create(['product_id' => $productId, 'quantity' => $random->getInt(2, 12)]);
            }
        }

        $user = User::firstOrCreate(['email' => 'demo@el3ktron.test'], ['name' => 'Demo', 'password' => bcrypt(bin2hex(random_bytes(16)))]);
        $this->command?->info('API token: '.$user->createToken('demo')->plainTextToken);
    }

    private function writeCatalogue(string $path, Randomizer $random): void
    {
        @mkdir(dirname($path), 0775, true);
        $out = fopen($path, 'w');
        fputcsv($out, ['sku', 'ean', 'name', 'family_code', 'price_cents', 'status'], escape: '');
        for ($i = 1; $i <= 600; $i++) {
            $family = self::FAMILIES[$random->getInt(0, count(self::FAMILIES) - 1)];
            fputcsv($out, [
                sprintf('E3-%05d', $i),
                sprintf('84123450%05d', $i),
                ucfirst(strtolower($family))." modelo {$i}",
                $family,
                $random->getInt(100, 5000),
                $random->getInt(1, 100) <= 3 ? 'discontinued' : 'active',
            ], escape: '');
        }
        fclose($out);
    }

    /** @return list<int> location ids: 12 aisles × 20 bays × 2 levels */
    private function locations(): array
    {
        $ids = [];
        foreach (range(1, 12) as $aisle) {
            foreach (range(1, 20) as $bay) {
                foreach (range(1, 2) as $level) {
                    $ids[] = Location::create(['code' => sprintf('A%02d-%02d-%d', $aisle, $bay, $level), 'aisle' => $aisle, 'bay' => $bay, 'level' => $level])->id;
                }
            }
        }

        return $ids;
    }
}
