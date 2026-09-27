<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets PlatformAuditLogger add a line to a tenant's activity log that no
     * tenant user performed (e.g. "Account suspended"): user_id may be null,
     * and platform_actor names who acted ("Zyncwork support").
     *
     * The composite (user_id, tenant_id) foreign key stays as it is; a row
     * with a null user_id is simply not checked against users.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('platform_actor', 100)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        // Making user_id required again would mean deleting the tenant-visible
        // record of platform actions; refuse rather than lose it silently.
        if (DB::table('activity_logs')->whereNull('user_id')->exists()) {
            throw new RuntimeException('activity_logs has platform entries with no user_id; remove or reassign them before rolling back.');
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('platform_actor');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
