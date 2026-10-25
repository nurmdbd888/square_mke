<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        $timestamp = now();
        $seen = [];
        $brands = [];

        foreach (DB::table('products')->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand') as $brand) {
            $name = trim((string) $brand);
            $key = mb_strtolower($name);

            if ($name === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $brands[] = ['name' => $name, 'created_at' => $timestamp, 'updated_at' => $timestamp];
        }

        if ($brands !== []) {
            DB::table('brands')->insert($brands);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
