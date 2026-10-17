<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 16);
            $table->string('layout', 24)->default('flat');
            $table->string('original_name');
            $table->string('stored_path')->nullable();
            $table->string('status', 16)->default('preview');
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->foreignId('mapping_profile_id')->nullable()->constrained('import_mapping_profiles')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->json('header_warnings')->nullable();
            $table->json('posted_document_ids')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_import_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no', 64);
            $table->json('header');
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 16)->default('ok');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_import_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('line_type', 16)->default('invoice');
            $table->string('section', 32)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_code', 32)->nullable();
            $table->string('product_label');
            $table->string('pack_size', 64)->nullable();
            $table->decimal('qty', 14, 3)->default(0);
            $table->decimal('bonus_qty', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('line_discount', 14, 2)->default(0);
            $table->decimal('line_value', 14, 2)->nullable();
            $table->decimal('sd_pct', 7, 4)->default(0);
            $table->decimal('sd_amount', 14, 2)->default(0);
            $table->decimal('vat_pct', 7, 4)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_incl_tax', 14, 2)->nullable();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_import_lines');
        Schema::dropIfExists('document_import_invoices');
        Schema::dropIfExists('document_import_batches');
    }
};
