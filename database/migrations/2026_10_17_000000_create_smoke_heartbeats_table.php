<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smoke_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ran_at')->index();
            $table->string('source', 20)->default('cron');
            $table->string('php_version', 20);
            $table->string('php_binary', 191)->nullable();
            $table->string('hostname', 191)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smoke_heartbeats');
    }
};
