<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plans a tenant can be on. Platform-wide, not tenant-owned.
     *
     * Pricing is per seat: a paid tenant's seat limit is the quantity it pays
     * for (tenant_subscriptions.seats), so included_seats is null for paid
     * plans. The trial plan includes a fixed number of seats instead.
     *
     * provider_price_id is the billing provider's price (Paddle), filled in
     * once the provider is connected; nothing reads it yet.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name');
            $table->unsignedInteger('included_seats')->nullable();
            $table->string('provider_price_id')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // The app needs these rows to work (every tenant gets the trial), so
        // they ship with the schema rather than in a seeder.
        DB::table('plans')->insert([
            ['key' => 'trial', 'name' => 'Trial', 'included_seats' => 10, 'is_trial' => true, 'is_active' => true, 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'standard', 'name' => 'Standard', 'included_seats' => null, 'is_trial' => false, 'is_active' => true, 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
