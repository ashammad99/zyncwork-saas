<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every table holding a tenant's data. Child tables (tasks, time_logs,
     * comments, ...) carry tenant_id directly rather than deriving it through
     * their parents, so the tenant filter is always a single indexed column.
     */
    private const TABLES = [
        'users',
        'departments',
        'projects',
        'tasks',
        'time_logs',
        'comments',
        'notifications',
        'activity_logs',
        'settings',
        // No model or routes yet, but Task::attachments() is stubbed out; scope it before it ships.
        'attachments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->index()
                    ->constrained('tenants')->restrictOnDelete();
            });
        }

        // Existing single-tenant data all belongs to one tenant.
        $hasData = collect(self::TABLES)->contains(fn ($name) => DB::table($name)->exists());

        if ($hasData) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => config('app.name', 'Default'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (self::TABLES as $name) {
                DB::table($name)->update(['tenant_id' => $tenantId]);
            }
        }

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) {
                // The explicit index must go too, or SQLite refuses to drop the column.
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
