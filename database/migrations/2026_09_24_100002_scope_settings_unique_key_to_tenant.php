<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each tenant has its own settings, so a key is unique per tenant.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['setting_key']);
            $table->unique(['tenant_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'setting_key']);
            $table->unique('setting_key');
        });
    }
};
