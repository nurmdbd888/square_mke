<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name_en', 191);
            $table->string('name_bn', 191)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('pack_size', 80)->nullable();
            $table->string('unit', 20)->default('Pcs');
            $table->unsignedInteger('units_per_carton')->nullable();
            $table->decimal('buy_price', 14, 4)->nullable();
            $table->decimal('buy_price_exact', 14, 4)->nullable();
            $table->decimal('sell_price', 14, 4)->nullable();
            $table->decimal('reorder_level', 14, 2)->nullable();
            $table->decimal('sd_pct', 5, 2)->default(0);
            $table->decimal('vat_pct', 5, 2)->default(15);
            $table->boolean('tracks_expiry')->default(false);
            $table->json('custom')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('brand');
            $table->index('name_en');
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 191);
            $table->string('bin', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 120);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_reps', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->nullable();
            $table->string('registration', 40);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('registration');
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 14, 4);
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id', 'effective_from'], 'price_list_items_unique');
            $table->index(['product_id', 'effective_from']);
        });

        Schema::create('retailers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 191);
            $table->string('address', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->json('custom')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
            $table->index('phone');
        });

        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->date('as_of');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('retailer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('note', 191)->nullable();
            $table->timestamps();
            $table->index(['kind', 'as_of']);
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 20);
            $table->string('key', 40);
            $table->string('label', 80);
            $table->string('type', 20)->default('string');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['entity', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('opening_balances');
        Schema::dropIfExists('retailers');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('sales_reps');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('product_aliases');
        Schema::dropIfExists('products');
    }
};
