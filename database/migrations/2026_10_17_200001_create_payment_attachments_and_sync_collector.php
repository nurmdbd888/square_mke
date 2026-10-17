<?php

use App\Support\Access\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('payment_id');
        });

        app(PermissionRegistry::class)->sync();

        $collector = Role::findByName('collector');
        foreach (['payments.view', 'payments.collect', 'retailers.view'] as $permission) {
            $collector->givePermissionTo($permission);
        }

        Role::findByName('sr')->givePermissionTo('payments.collect');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attachments');
    }
};
