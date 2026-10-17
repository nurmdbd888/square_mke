<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('collection_status', 20)->nullable()->after('status');
            $table->timestamp('collection_confirmed_at')->nullable()->after('collection_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['collection_status', 'collection_confirmed_at']);
        });
    }
};
