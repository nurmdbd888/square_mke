<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_import_lines', function (Blueprint $table) {
            $table->string('batch_no', 64)->nullable();
            $table->decimal('mrp', 14, 2)->nullable();
            $table->boolean('provisional_cost')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('document_import_lines', function (Blueprint $table) {
            $table->dropColumn(['batch_no', 'mrp', 'provisional_cost']);
        });
    }
};
