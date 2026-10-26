<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price of each paid plan, shown on the public pricing page.
     *
     * price_amount is in minor units (cents) per seat per interval. All three
     * are null on the trial plan, which costs nothing. Paddle charges from its
     * own price (provider_price_id); this copy must match it.
     *
     * The Standard values are placeholders until the real price is set.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('price_amount')->nullable()->after('included_seats');
            $table->char('currency', 3)->nullable()->after('price_amount');
            $table->string('interval', 10)->nullable()->after('currency');
        });

        DB::table('plans')->where('key', 'standard')->update([
            'price_amount' => 1000,
            'currency' => 'USD',
            'interval' => 'month',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_amount', 'currency', 'interval']);
        });
    }
};
