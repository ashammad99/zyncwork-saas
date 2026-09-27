<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Live state of each support session (see PlatformImpersonation).
     * The permanent history is in platform_audit_logs, which has no foreign keys.
     */
    public function up(): void
    {
        Schema::create('platform_impersonations', function (Blueprint $table) {
            $table->id();
            // Platform admins are deactivated, never deleted.
            $table->foreignId('platform_admin_id')->constrained('platform_admins')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('target_user_id');
            $table->string('reason', 500);

            // SHA-256 of the one-time hand-off token; the token itself is never stored.
            $table->char('token_hash', 64)->unique();
            $table->timestamp('token_expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 20)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Same-tenant link, as in enforce_same_tenant_links. Deleting the user
            // drops the live row (ending the session); the audit log keeps the history.
            $table->index(['target_user_id', 'tenant_id']);
            $table->foreign(['target_user_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_impersonations');
    }
};
