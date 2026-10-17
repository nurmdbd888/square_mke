<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // effective_from is null for settings that are not effective-dated;
        // App\Settings\SettingsStore keeps a single row per key for those.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->date('effective_from')->nullable();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['key', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
