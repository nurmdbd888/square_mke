<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('label', 191);
            $table->json('payload')->nullable();
            $table->string('result_path', 512)->nullable();
            $table->string('result_mime', 128)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('google_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('google_email', 191)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('drive_folder_id', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('google_sheet_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('export_key', 64);
            $table->string('title', 191);
            $table->string('spreadsheet_id', 128);
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->string('refresh', 32)->default('daily');
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('next_refresh_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheet_subscriptions');
        Schema::dropIfExists('google_connections');
        Schema::dropIfExists('app_jobs');
    }
};
