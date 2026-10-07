<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each tenant's plan and billing state: the only thing seat limits are
     * enforced against. A billing provider (Paddle) will write into it from
     * webhooks later; nothing in the app reads the provider's own tables.
     *
     * Platform-side like tenants (no tenant_id scope): one row per tenant,
     * reached through Tenant::subscription().
     *
     * status is a string, not a DB enum: trialing | active | past_due |
     * canceled | none (constants on TenantSubscription).
     */
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('seats');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->timestamps();
        });

        // Every existing tenant starts the trial now, as a new signup would.
        $trial = DB::table('plans')->where('key', 'trial')->first();
        $now = now();
        $trialEndsAt = $now->copy()->addDays((int) config('billing.trial_days', 14));

        DB::table('tenants')->orderBy('id')->chunkById(500, function ($tenants) use ($trial, $now, $trialEndsAt) {
            DB::table('tenant_subscriptions')->insert($tenants->map(fn ($tenant) => [
                'tenant_id' => $tenant->id,
                'plan_id' => $trial->id,
                'status' => 'trialing',
                'seats' => $trial->included_seats,
                'trial_ends_at' => $trialEndsAt,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
