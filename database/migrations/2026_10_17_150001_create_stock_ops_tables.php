<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 80);
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_no']);
            $table->index(['product_id', 'expiry_date']);
        });

        Schema::table('document_lines', function (Blueprint $table) {
            $table->string('batch_no', 80)->nullable()->after('product_id');
            $table->date('expiry_date')->nullable()->after('batch_no');
            $table->foreignId('product_batch_id')->nullable()->after('expiry_date')->constrained('product_batches')->nullOnDelete();
        });

        Schema::create('stock_notes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('number', 80);
            $table->date('doc_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('retailer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->decimal('net_total', 14, 2)->default(0);
            $table->decimal('stock_loss_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'doc_date']);
            $table->index(['retailer_id', 'number']);
        });

        Schema::create('stock_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_note_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('line_condition', 20)->default('good');
            $table->decimal('qty', 14, 3)->default(0);
            $table->decimal('expected_qty', 14, 3)->nullable();
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('line_value', 14, 2)->default(0);
            $table->foreignId('linked_document_line_id')->nullable()->constrained('document_lines')->nullOnDelete();
            $table->decimal('restore_cost', 14, 2)->nullable();
            $table->decimal('loss_cost', 14, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['stock_note_id', 'line_no']);
        });

        Schema::table('stock_ledger_entries', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->after('product_id')->constrained('product_batches')->nullOnDelete();
            $table->foreignId('stock_note_id')->nullable()->after('document_line_id')->constrained('stock_notes')->nullOnDelete();
            $table->foreignId('stock_note_line_id')->nullable()->after('stock_note_id')->constrained('stock_note_lines')->nullOnDelete();
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('number', 80);
            $table->date('doc_date');
            $table->string('status', 20)->default('open');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'doc_date']);
        });

        Schema::create('scheme_lines', function (Blueprint $table) {
            $table->id();
            $table->date('doc_date');
            $table->string('kind', 10);
            $table->string('label', 191);
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'doc_date']);
        });

        Schema::create('stock_comparisons', function (Blueprint $table) {
            $table->id();
            $table->string('original_name', 191);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_comparison_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_comparison_id')->constrained()->cascadeOnDelete();
            $table->string('product_code', 80)->nullable();
            $table->string('product_name', 191)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('our_qty', 14, 3)->nullable();
            $table->decimal('their_qty', 14, 3)->nullable();
            $table->string('status', 30);
            $table->timestamps();

            $table->index('stock_comparison_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_comparison_lines');
        Schema::dropIfExists('stock_comparisons');
        Schema::dropIfExists('scheme_lines');
        Schema::dropIfExists('claims');

        Schema::table('stock_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_note_line_id');
            $table->dropConstrainedForeignId('stock_note_id');
            $table->dropConstrainedForeignId('product_batch_id');
        });

        Schema::dropIfExists('stock_note_lines');
        Schema::dropIfExists('stock_notes');

        Schema::table('document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_batch_id');
            $table->dropColumn(['batch_no', 'expiry_date']);
        });

        Schema::dropIfExists('product_batches');
    }
};
