<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of every platform-admin action (PlatformAuditLog).
     *
     * Deliberately no foreign keys: an audit entry must outlive whatever it
     * mentions, and must never block (or be removed by) deleting a tenant,
     * user or support session. Only created_at, since entries never change.
     */
    public function up(): void
    {
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            // Null for failed logins, where there is no admin yet.
            $table->unsignedBigInteger('platform_admin_id')->nullable()->index();
            $table->string('action', 64)->index();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->unsignedBigInteger('impersonation_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
    }
};
