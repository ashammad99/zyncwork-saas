<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Keep the newest row per key (the one Setting::allKeyed() already
        // resolves to) and drop older duplicates so the unique index can be added.
        $keepIds = DB::table('settings')
            ->selectRaw('MAX(id) as id')
            ->groupBy('setting_key')
            ->pluck('id');

        DB::table('settings')->whereNotIn('id', $keepIds)->delete();

        Schema::table('settings', function (Blueprint $table) {
            $table->unique('setting_key');
        });

        Cache::forget('app.settings.all');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['setting_key']);
        });
    }
};
