<?php

use Database\Seeders\ProductSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Maintenance → Run migrations is the only step on the server. The seeder is idempotent.
        (new ProductSeeder)->run();
    }

    public function down(): void
    {
        // The products table migration removes these rows with the table.
    }
};
