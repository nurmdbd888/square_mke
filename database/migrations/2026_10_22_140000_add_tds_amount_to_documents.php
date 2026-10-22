<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Square Toiletries "Invoice Adjustment" Total TDS Amount.
 * Added to payable: Total Payable = (subtotal − loading − combo) + TDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->decimal('tds_amount', 14, 2)->default(0)->after('combo_bonus_discount');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('tds_amount');
        });
    }
};
