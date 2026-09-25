<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every link between two tenant-owned rows: child table => [column => parent table].
     *
     * Each gets a composite foreign key (column, tenant_id) → parent(id, tenant_id),
     * so the database itself rejects a row pointing at another tenant's row, even
     * when the write skips validation and the model layer (raw queries, pivot
     * sync, bugs). The existing single-column foreign keys stay as they are.
     * A NULL link column is not checked, as before.
     */
    private const LINKS = [
        'users' => ['department_id' => 'departments'],
        'departments' => ['manager_id' => 'users'],
        'projects' => ['department_id' => 'departments', 'owner_id' => 'users'],
        'project_members' => ['project_id' => 'projects', 'user_id' => 'users'],
        'tasks' => [
            'project_id' => 'projects',
            'assignee_id' => 'users',
            'created_by' => 'users',
            'parent_task_id' => 'tasks',
            'depends_on_task_id' => 'tasks',
        ],
        'time_logs' => ['task_id' => 'tasks', 'user_id' => 'users', 'approved_by' => 'users'],
        'comments' => ['task_id' => 'tasks', 'user_id' => 'users', 'parent_id' => 'comments'],
        'notifications' => ['user_id' => 'users'],
        'activity_logs' => ['user_id' => 'users'],
        'attachments' => ['task_id' => 'tasks', 'user_id' => 'users'],
    ];

    /** Mirrors the existing single-column foreign keys' ON DELETE CASCADE. */
    private const CASCADE = ['notifications.user_id', 'activity_logs.user_id'];

    private const PARENTS = ['users', 'departments', 'projects', 'tasks', 'comments'];

    public function up(): void
    {
        // project_members is a pivot without a model, so it had no tenant_id yet.
        Schema::table('project_members', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('user_id');
        });

        DB::table('project_members')->update([
            'tenant_id' => DB::raw('(select tenant_id from projects where projects.id = project_members.project_id)'),
        ]);

        Schema::table('project_members', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });

        // A composite foreign key needs a unique key on the parent's (id, tenant_id).
        foreach (self::PARENTS as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unique(['id', 'tenant_id']);
            });
        }

        foreach (self::LINKS as $name => $links) {
            Schema::table($name, function (Blueprint $table) use ($name, $links) {
                foreach ($links as $column => $parent) {
                    // Explicit, so MySQL uses it (instead of creating its own) and down() can drop it.
                    $table->index([$column, 'tenant_id']);

                    $foreign = $table->foreign([$column, 'tenant_id'])
                        ->references(['id', 'tenant_id'])
                        ->on($parent);

                    if (in_array("{$name}.{$column}", self::CASCADE, true)) {
                        $foreign->cascadeOnDelete();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::LINKS, true) as $name => $links) {
            $restore = $this->indexesToRestore($name, array_keys($links));

            Schema::table($name, function (Blueprint $table) use ($name, $links, $restore) {
                foreach (array_keys($links) as $column) {
                    $table->dropForeign([$column, 'tenant_id']);

                    if (in_array($column, $restore, true)) {
                        $table->index($column, "{$name}_{$column}_foreign");
                    }

                    $table->dropIndex([$column, 'tenant_id']);
                }
            });
        }

        foreach (self::PARENTS as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['id', 'tenant_id']);
            });
        }

        Schema::table('project_members', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }

    /**
     * MySQL gives each single-column foreign key its own index, then silently
     * drops it once up() adds (column, tenant_id), which the key then uses
     * instead. Put that index back (under MySQL's own name) before dropping
     * the composite one, unless another index still leads with the column.
     */
    private function indexesToRestore(string $name, array $columns): array
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return [];
        }

        $indexes = Schema::getIndexes($name);

        return array_values(array_filter($columns, fn ($column) => ! collect($indexes)->contains(
            fn ($index) => $index['columns'][0] === $column && $index['columns'] !== [$column, 'tenant_id']
        )));
    }
};
