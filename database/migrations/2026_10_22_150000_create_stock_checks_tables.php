<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();
        });

        Schema::create('stock_check_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('in_store', 20);
            $table->decimal('on_hand_qty', 14, 3)->default(0);
            $table->decimal('on_hand_value', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['stock_check_id', 'product_id']);
            $table->index('stock_check_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_check_lines');
        Schema::dropIfExists('stock_checks');
    }
};
