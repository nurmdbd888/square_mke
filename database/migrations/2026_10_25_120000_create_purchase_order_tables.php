<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['supplier_id', 'order_date']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->decimal('qty', 14, 3);
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'line_no']);
            $table->index('product_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->index('purchase_order_id');
        });

        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('number', 80)->unique();
            $table->date('received_on');
            $table->string('challan_no', 80)->nullable();
            $table->string('vehicle_no', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['purchase_order_id', 'received_on']);
        });

        Schema::create('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->decimal('due_qty', 14, 3)->default(0);
            $table->string('status', 20);
            $table->timestamps();
            $table->unique(['purchase_order_receipt_id', 'purchase_order_line_id'], 'po_receipt_line_unique');
        });

        Schema::create('purchase_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('payment_id');
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_payments');
        Schema::dropIfExists('purchase_order_receipt_lines');
        Schema::dropIfExists('purchase_order_receipts');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
