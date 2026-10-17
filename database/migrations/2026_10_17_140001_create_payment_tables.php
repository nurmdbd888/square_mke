<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $categories = [
            ['key' => 'van_rent', 'label' => 'Van rent', 'sort' => 10],
            ['key' => 'fuel', 'label' => 'Fuel', 'sort' => 20],
            ['key' => 'toll', 'label' => 'Toll', 'sort' => 30],
            ['key' => 'loading', 'label' => 'Loading and unloading', 'sort' => 40],
            ['key' => 'godown_rent', 'label' => 'Godown rent', 'sort' => 50],
            ['key' => 'sr_salary', 'label' => 'SR salary and TA-DA', 'sort' => 60],
            ['key' => 'mobile', 'label' => 'Mobile bill', 'sort' => 70],
            ['key' => 'trade_licence', 'label' => 'Trade licence', 'sort' => 80],
            ['key' => 'other', 'label' => 'Other', 'sort' => 90],
        ];
        foreach ($categories as $row) {
            DB::table('expense_categories')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // receipt | payment
            $table->string('number', 80);
            $table->foreignId('retailer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20); // cash, bkash, nagad, rocket, bank, cheque, pdc, petty
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->date('due_date')->nullable();
            $table->string('clearing_status', 20)->nullable(); // pending | cleared | bounced
            $table->timestamp('cleared_at')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('cheque_number', 80)->nullable();
            $table->foreignId('sales_rep_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_advance')->default(false);
            $table->string('status', 20)->default('draft'); // draft | posted | void
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'status', 'payment_date']);
            $table->index(['retailer_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['method', 'clearing_status']);
            $table->index('number');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'document_id']);
            $table->index('document_id');
        });

        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closed_on')->unique();
            $table->decimal('cash_opening', 14, 2)->default(0);
            $table->decimal('cash_in', 14, 2)->default(0);
            $table->decimal('cash_out', 14, 2)->default(0);
            $table->decimal('cash_closing', 14, 2)->default(0);
            $table->decimal('bank_opening', 14, 2)->default(0);
            $table->decimal('bank_in', 14, 2)->default(0);
            $table->decimal('bank_out', 14, 2)->default(0);
            $table->decimal('bank_closing', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('expense_categories');
    }
};
