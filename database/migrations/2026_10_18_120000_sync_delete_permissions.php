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
        // New delete permissions remain; revoke via Roles UI if rolling back intentionally.
    }
};
