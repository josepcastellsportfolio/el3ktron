<?php

namespace App\Providers;

use App\Catalog\CsvErpFeed;
use App\Catalog\ErpFeed;
use App\Orders\LlmExtractor;
use App\Orders\OrderExtractor;
use App\Orders\RuleBasedExtractor;
use App\Warehouse\WarehouseLayout;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The seams: where the catalogue comes from, which extractor reads
     * orders, and the warehouse geometry. Swapping one is a config change.
     */
    public function register(): void
    {
        $this->app->bind(ErpFeed::class, fn () => new CsvErpFeed((string) config('el3ktron.sync.catalog_csv')));

        $this->app->bind(OrderExtractor::class, fn () => self::extractor((string) config('el3ktron.orders.extractor')));

        $this->app->singleton(WarehouseLayout::class, fn () => WarehouseLayout::fromConfig());
    }

    public function boot(): void
    {
        // Lazy loading in a loop is the classic N+1: fail loudly outside production.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Per token (or IP when anonymous): one runaway integrator cannot starve the rest.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }

    public static function extractor(string $name): OrderExtractor
    {
        return match ($name) {
            'llm' => new LlmExtractor(
                config('el3ktron.ai.api_key'),
                (string) config('el3ktron.ai.model'),
                (string) config('el3ktron.ai.base_url'),
                (int) config('el3ktron.ai.timeout_seconds'),
            ),
            default => new RuleBasedExtractor((string) config('el3ktron.orders.sku_pattern')),
        };
    }
}
