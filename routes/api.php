<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InstallationController;
use App\Http\Controllers\Api\V1\OrderDraftController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{sku}', [ProductController::class, 'show']);

        Route::post('installations', [InstallationController::class, 'store']);
        Route::get('installations/{installation}', [InstallationController::class, 'show']);
        Route::post('installations/{installation}/check', [InstallationController::class, 'check']);
        Route::post('installations/{installation}/confirm-review', [InstallationController::class, 'confirmReview']);
        Route::post('installations/{installation}/reserve', [InstallationController::class, 'reserve']);
        Route::get('installations/{installation}/picking-list', [InstallationController::class, 'pickingList']);

        Route::get('alerts', [AlertController::class, 'index']);
        Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve']);

        Route::post('order-drafts', [OrderDraftController::class, 'store']);
        Route::get('order-drafts/{draft}', [OrderDraftController::class, 'show']);
        Route::put('order-drafts/{draft}', [OrderDraftController::class, 'update']);
        Route::post('order-drafts/{draft}/approve', [OrderDraftController::class, 'approve']);
        Route::post('order-drafts/{draft}/reject', [OrderDraftController::class, 'reject']);

        Route::post('sync/catalog', [SyncController::class, 'catalog']);
        Route::get('sync/runs', [SyncController::class, 'runs']);
    });
});
