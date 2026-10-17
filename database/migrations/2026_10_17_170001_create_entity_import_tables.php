<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mapping_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 64);
            $table->string('name', 120);
            $table->json('mapping')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('entity_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 64);
            $table->string('original_name');
            $table->string('status', 16)->default('preview');
            $table->string('duplicate_mode', 16)->default('update');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->foreignId('mapping_profile_id')->nullable()->constrained('import_mapping_profiles')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('entity_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_no');
            $table->json('raw')->nullable();
            $table->json('parsed')->nullable();
            $table->string('status', 16)->default('ok');
            $table->text('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_import_rows');
        Schema::dropIfExists('entity_import_batches');
        Schema::dropIfExists('import_mapping_profiles');
    }
};
