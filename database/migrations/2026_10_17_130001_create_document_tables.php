<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // purchase | sale
            $table->string('number', 80);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('retailer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('doc_date');
            $table->date('delivery_date')->nullable();
            $table->string('status', 20)->default('draft'); // draft | posted | void
            $table->foreignId('sales_rep_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('header_discount', 14, 2)->default(0);
            $table->decimal('slab_adjustment', 14, 2)->default(0);
            $table->decimal('combo_bonus_discount', 14, 2)->default(0);
            $table->decimal('rounding_adjustment', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->nullable();
            $table->decimal('cogs', 14, 2)->nullable();
            $table->decimal('free_goods_cost', 14, 2)->nullable();
            $table->decimal('commission_gross', 14, 2)->nullable();
            $table->decimal('commission_vat', 14, 2)->nullable();
            $table->decimal('commission_net', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'doc_date']);
            $table->index(['type', 'number']);
            $table->index(['supplier_id', 'number', 'net_total']);
            $table->index(['retailer_id', 'number', 'net_total']);
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20)->default('normal'); // normal | combo_bonus
            $table->decimal('qty', 14, 3)->default(0);
            $table->decimal('bonus_qty', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('line_discount', 14, 2)->default(0);
            $table->decimal('header_discount_share', 14, 2)->default(0);
            $table->decimal('line_value', 14, 2)->default(0);
            $table->decimal('sd_pct', 7, 4)->default(0);
            $table->decimal('vat_pct', 7, 4)->default(0);
            $table->decimal('sd_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('issue_cost', 14, 2)->nullable();
            $table->decimal('bonus_issue_cost', 14, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'line_no']);
            $table->index('product_id');
        });

        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 255);
            $table->string('original_name', 191);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('doc_date');
            $table->string('movement', 30);
            $table->unsignedTinyInteger('rank');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_line_id')->nullable()->constrained('document_lines')->nullOnDelete();
            $table->decimal('qty_in', 14, 3)->default(0);
            $table->decimal('qty_out', 14, 3)->default(0);
            $table->decimal('value_in', 14, 2)->default(0);
            $table->decimal('value_out', 14, 2)->default(0);
            $table->decimal('running_qty', 14, 3)->default(0);
            $table->decimal('running_value', 14, 2)->default(0);
            $table->decimal('average_cost', 18, 6)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'doc_date', 'rank', 'posted_at', 'id'], 'stock_ledger_order_idx');
            $table->index(['document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger_entries');
        Schema::dropIfExists('document_attachments');
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
    }
};
