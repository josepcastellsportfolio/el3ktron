<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue (mirrored from the ERP), the warehouse (locations and stock),
 * stores, planograms and the installations built from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 32)->unique();
            $table->string('ean', 13)->nullable()->unique();
            $table->string('name');
            $table->foreignId('family_id')->constrained();
            $table->unsignedInteger('price_cents');
            $table->string('status', 16)->default('active');
            // sha1 of the last ERP row applied: an unchanged row is skipped.
            $table->char('erp_hash', 40)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            // Substitute lookup: same family, active.
            $table->index(['family_id', 'status']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->unsignedSmallInteger('aisle');
            $table->unsignedSmallInteger('bay');
            $table->unsignedSmallInteger('level');
            $table->timestamps();
            $table->index(['aisle', 'bay']);
        });

        Schema::create('stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'location_id']);
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->timestamps();
        });

        Schema::create('planograms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('planogram_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planogram_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['planogram_id', 'product_id']);
        });

        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('planogram_id')->constrained();
            $table->string('status', 16)->default('draft');
            $table->date('scheduled_for')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('review_reason')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('installation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            // Set when this line replaces a planogram product that was unavailable.
            $table->foreignId('substitute_for_product_id')->nullable()->constrained('products');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('stock');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('status', 16);
            $table->unsignedInteger('rows_seen')->default(0);
            $table->unsignedInteger('rows_changed')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['source', 'status', 'finished_at']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('severity', 16);
            // Same type + key while unresolved = same alert, raised once.
            $table->string('dedupe_key');
            $table->string('message');
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['dedupe_key', 'resolved_at']);
        });

        Schema::create('order_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained();
            $table->string('extractor', 16);
            $table->string('status', 16)->default('pending');
            $table->longText('source_text');
            $table->json('lines');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['order_drafts', 'alerts', 'sync_runs', 'reservations', 'installation_lines', 'installations',
            'planogram_items', 'planograms', 'stores', 'stock', 'locations', 'products', 'families'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
