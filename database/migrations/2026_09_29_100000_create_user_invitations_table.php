<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invitations to join a tenant (UserInvitation). Tenant-owned: every link
     * to users and departments is a composite (id, tenant_id) foreign key, as
     * in enforce_same_tenant_links, so an invite can never point across tenants.
     */
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('invited_by');

            // SHA-256 of the emailed token; the token itself is never stored.
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'email']);

            // A pending invite into a deleted department is meaningless: drop it with the department.
            $table->index(['department_id', 'tenant_id']);
            $table->foreign(['department_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('departments')
                ->cascadeOnDelete();

            $table->index(['invited_by', 'tenant_id']);
            $table->foreign(['invited_by', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('users');

            $table->index(['accepted_user_id', 'tenant_id']);
            $table->foreign(['accepted_user_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
