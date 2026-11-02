<?php

namespace App\Console\Commands;

use App\Benchmark\SyntheticWarehouse;
use App\Installations\Candidate;
use App\Installations\SubstitutePicker;
use App\Warehouse\PickingRoute;
use App\Warehouse\WarehouseLayout;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Measures the two savings that can be measured without real data, on a
 * seeded synthetic warehouse, with the same code the API runs:
 *
 * 1. Walking: S-shape route against two baselines, catalogue order (the
 *    worst case) and location order (the fair one).
 * 2. Incomplete installations: without the availability check vs with it and
 *    its substitutes (SubstitutePicker).
 *
 * Euros are a formula over the assumptions passed as options, printed next to
 * the result. Change the assumptions, not the code. The report is in
 * Spanish: it is written for the people who would use the tool.
 */
#[Signature('el3ktron:bench
    {--seed=42 : Random seed; same seed, same numbers}
    {--installations=200 : Synthetic installations to measure}
    {--aisles=12 : Aisles in the synthetic warehouse}
    {--products=2000 : Products in the catalogue}
    {--families=400 : Product families; substitutes come from the same family}
    {--shortage-rate=0.015 : Share of products without enough free stock}
    {--discontinued-rate=0.005 : Share of discontinued products}
    {--walk-speed=0.8 : Picker speed with a trolley, m/s}
    {--hourly-cost=22 : Loaded cost of one warehouse hour, EUR}
    {--shipment-cost=35 : Cost of one extra shipment to a store, EUR}
    {--per-year=500 : Installations prepared per year}
    {--write : Also write docs/BENCHMARK.md}')]
#[Description('Benchmark picking routes and the availability check on synthetic data')]
class Bench extends Command
{
    public function handle(): int
    {
        $layout = WarehouseLayout::fromConfig();
        $data = new SyntheticWarehouse(
            seed: (int) $this->option('seed'),
            aisles: (int) $this->option('aisles'),
            baysPerAisle: $layout->baysPerAisle,
            productCount: (int) $this->option('products'),
            families: (int) $this->option('families'),
            installationCount: (int) $this->option('installations'),
            shortageRate: (float) $this->option('shortage-rate'),
            discontinuedRate: (float) $this->option('discontinued-rate'),
        );

        $report = $this->report($data, $layout, $this->walking($data, $layout), $this->incomplete($data));

        $this->line($report);
        if ($this->option('write')) {
            file_put_contents(base_path('docs/BENCHMARK.md'), $report);
            $this->info('Wrote docs/BENCHMARK.md');
        }

        return self::SUCCESS;
    }

    /** @return array{catalogue: float, location: float, route: float} mean meters per installation */
    private function walking(SyntheticWarehouse $data, WarehouseLayout $layout): array
    {
        $sum = ['catalogue' => 0.0, 'location' => 0.0, 'route' => 0.0];
        foreach ($data->installations as $items) {
            $lines = $data->pickLines($items);
            $sum['catalogue'] += $layout->routeLength(PickingRoute::points(PickingRoute::catalogueOrder($lines)));
            $sum['location'] += $layout->routeLength(PickingRoute::points(PickingRoute::locationOrder($lines)));
            $sum['route'] += $layout->routeLength(PickingRoute::points(PickingRoute::serpentine($lines)));
        }
        $n = count($data->installations);

        return array_map(fn (float $meters) => $meters / $n, $sum);
    }

    /** @return array{without: int, with: int, substituted: int, missing: int} */
    private function incomplete(SyntheticWarehouse $data): array
    {
        $families = $data->byFamily();
        $result = ['without' => 0, 'with' => 0, 'substituted' => 0, 'missing' => 0];

        // Each installation is checked against fresh stock: a simplification, stated in the report.
        foreach ($data->installations as $items) {
            $free = array_map(fn (Candidate $p) => $p->available, $data->products);
            $exclude = array_fill_keys(array_keys($items), true);
            $without = $with = false;

            foreach ($items as $id => $quantity) {
                $product = $data->products[$id];
                if ($product->active && $free[$id] >= $quantity) {
                    $free[$id] -= $quantity;

                    continue;
                }
                $without = true;
                $candidates = array_map(fn (Candidate $c) => new Candidate($c->productId, $c->familyId, $c->priceCents, $free[$c->productId], $c->active), $families[$product->familyId]);
                $substitute = SubstitutePicker::pick($product, $quantity, $candidates, $exclude);
                if ($substitute === null) {
                    $with = true;
                    $result['missing']++;

                    continue;
                }
                $free[$substitute->productId] -= $quantity;
                $exclude[$substitute->productId] = true;
                $result['substituted']++;
            }

            $result['without'] += (int) $without;
            $result['with'] += (int) $with;
        }

        return $result;
    }

    /**
     * @param  array{catalogue: float, location: float, route: float}  $walk
     * @param  array{without: int, with: int, substituted: int, missing: int}  $trips
     */
    private function report(SyntheticWarehouse $data, WarehouseLayout $layout, array $walk, array $trips): string
    {
        $n = count($data->installations);
        $speed = (float) $this->option('walk-speed');
        $hourly = (float) $this->option('hourly-cost');
        $shipment = (float) $this->option('shipment-cost');
        $perYear = (int) $this->option('per-year');

        $f = fn (float $v, int $d = 0) => number_format($v, $d, ',', '.');
        $pct = fn (float $v) => $f($v * 100, 1).' %';
        $minutes = fn (float $meters) => $meters / $speed / 60;
        $euros = fn (float $meters) => $minutes($meters) / 60 * $hourly * $perYear;

        $vsLocation = $walk['location'] - $walk['route'];
        $vsCatalogue = $walk['catalogue'] - $walk['route'];
        $rateWithout = $trips['without'] / $n;
        $rateWith = $trips['with'] / $n;
        $tripEuros = ($rateWithout - $rateWith) * $perYear * $shipment;

        return <<<MD
            # Benchmark

            Generado con `php artisan el3ktron:bench --seed={$data->seed} --write`. Datos sintéticos:
            ninguna cifra sale de una empresa real. Misma semilla, mismos números. Los supuestos
            están al final y cada uno se cambia con una opción del comando.

            ## 1. Ruta de picking

            Metros medios por implantación, desde el muelle y de vuelta.

            | Orden de la lista | Metros | Ruta en S ahorra |
            |---|---:|---:|
            | Por SKU, como sale del catálogo (peor caso) | {$f($walk['catalogue'])} m | {$f($vsCatalogue)} m ({$pct($vsCatalogue / $walk['catalogue'])}) |
            | Por ubicación, cada pasillo desde el frente (referencia justa) | {$f($walk['location'])} m | {$f($vsLocation)} m ({$pct($vsLocation / $walk['location'])}) |
            | **Ruta en S** | **{$f($walk['route'])} m** | — |

            La cifra que cuenta es la segunda fila: comparar contra el orden del catálogo
            infla el ahorro, porque nadie recorre un almacén en orden de SKU si la lista
            ya viene por ubicación.

            ## 2. Comprobación de disponibilidad

            | | Implantaciones que saldrían incompletas |
            |---|---:|
            | Sin comprobar | {$trips['without']} de {$n} ({$pct($rateWithout)}) |
            | Comprobando y con sustitutas | {$trips['with']} de {$n} ({$pct($rateWith)}) |

            Líneas sustituidas: {$trips['substituted']}. Líneas sin sustituta: {$trips['missing']}.
            Las sustitutas son propuestas: la implantación queda marcada hasta que una persona las confirma.

            ## Euros (una fórmula, no una promesa)

            | | Al año |
            |---|---:|
            | Ruta en S frente a orden por ubicación: {$f($minutes($vsLocation), 1)} min × {$perYear} implantaciones × {$f($hourly)} €/h | {$f($euros($vsLocation))} € |
            | Ruta en S frente a orden de catálogo (peor caso): {$f($minutes($vsCatalogue), 1)} min × {$perYear} × {$f($hourly)} €/h | {$f($euros($vsCatalogue))} € |
            | Envíos incompletos evitados: ({$pct($rateWithout)} − {$pct($rateWith)}) × {$perYear} × {$f($shipment)} € | {$f($tripEuros)} € |

            La última fila supone que cada implantación incompleta cuesta un envío extra. Si la tienda
            acepta recibirla incompleta, el coste es otro (un lineal con huecos), pero no es cero.

            ## Supuestos

            - Almacén: {$data->aisles} pasillos × {$layout->baysPerAisle} huecos, {$f($layout->aisleSpacingMeters, 1)} m entre pasillos, {$f($layout->bayWidthMeters, 1)} m por hueco, muelle al frente del pasillo 0.
            - {$data->productCount} productos en {$data->families} familias, una ubicación cada uno, colocados al azar.
            - {$n} implantaciones de {$data->minItems} a {$data->maxItems} productos, de 2 a 12 unidades por línea.
            - {$pct($data->shortageRate)} de productos sin stock suficiente, {$pct($data->discontinuedRate)} descatalogados.
            - Cada implantación se comprueba con el stock entero (no compiten entre ellas por las unidades).
            - El tiempo de coger cada línea es el mismo en todos los órdenes: solo se compara el caminar.
            - {$f($speed, 1)} m/s con carro, {$f($hourly)} €/h, {$f($shipment)} € por envío extra, {$perYear} implantaciones al año.

            MD;
    }
}
