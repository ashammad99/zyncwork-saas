<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the billing provider sends receipts and payment emails. Separate
     * from any user's login email, so it survives that admin leaving.
     * New signups get their first admin's email (SignupService).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_email')->nullable()->after('name');
        });

        // Existing tenants: their oldest active admin (a deactivated one's
        // mailbox may be gone). A tenant without one stays null.
        DB::table('tenants')->update([
            'billing_email' => DB::raw(
                "(select email from users where users.tenant_id = tenants.id and users.role = 'admin' and users.is_active = 1 order by users.id limit 1)"
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('billing_email');
        });
    }
};
