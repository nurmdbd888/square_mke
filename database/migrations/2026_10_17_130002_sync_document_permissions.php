<?php

use App\Support\Access\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistry::class)->sync();
    }

    public function down(): void
    {
        // Permissions stay; the owner may have changed the matrix.
    }
};
