<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['brand_id', 'name']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->decimal('purchase_discount_pct', 5, 2)->default(0);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->decimal('purchase_discount_pct', 5, 2)->nullable();
            $table->index('product_type_id');
        });

        $timestamp = now();
        foreach (DB::table('brands')->pluck('id') as $brandId) {
            DB::table('product_types')->insert([
                'brand_id' => $brandId,
                'name' => 'Uncategorized',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
        $brands = [];
        foreach (DB::table('products')->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand') as $legacyName) {
            $name = trim((string) $legacyName);
            if ($name === '') {
                continue;
            }

            $brandId = DB::table('brands')->where('name', $name)->value('id');
            if ($brandId === null) {
                $brandId = DB::table('brands')->insertGetId([
                    'name' => $name,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $typeId = DB::table('product_types')
                ->where('brand_id', $brandId)
                ->where('name', 'Uncategorized')
                ->value('id');
            if ($typeId === null) {
                $typeId = DB::table('product_types')->insertGetId([
                    'brand_id' => $brandId,
                    'name' => 'Uncategorized',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $brands[(string) $legacyName] = ['brand_id' => $brandId, 'type_id' => $typeId];
        }

        foreach ($brands as $name => $ids) {
            DB::table('products')
                ->where('brand', $name)
                ->whereNull('product_type_id')
                ->update(['brand_id' => $ids['brand_id'], 'product_type_id' => $ids['type_id']]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_type_id');
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn(['image_path', 'purchase_discount_pct']);
        });
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'purchase_discount_pct']);
        });
        Schema::dropIfExists('product_types');
    }
};
