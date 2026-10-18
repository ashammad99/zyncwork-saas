<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per billing email sent to a tenant, so each is sent once.
     *
     * Platform-side like tenant_subscriptions (written only by
     * BillingNotifier, never read by tenant-facing code), so it has a plain
     * tenant_id foreign key and no composite links.
     *
     * kind: trial_ends_3d | trial_ends_1d | trial_expired | seat_limit |
     * invitation_blocked (constants on TenantBillingNotice). period_key says
     * which occurrence it was: the trial end time for trial emails (an
     * extended trial gets new ones), the invitation id, or the send time for
     * the seat-limit email, whose 24-hour cooldown is checked on sent_at.
     * The unique key makes a second insert for the same occurrence a no-op,
     * even when two runs overlap.
     */
    public function up(): void
    {
        Schema::create('tenant_billing_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('period_key', 64);
            $table->timestamp('sent_at');

            $table->unique(['tenant_id', 'kind', 'period_key']);
            $table->index(['tenant_id', 'kind', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_notices');
    }
};
