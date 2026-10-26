<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification is enforced from Phase 1C (EnsureEmailIsVerified).
     * Accounts created before then were set up by an admin and never had a way
     * to verify, so they count as verified from the day they were created.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            // COALESCE and CURRENT_TIMESTAMP are portable; a row without created_at must not stay locked out.
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    /**
     * Nothing to undo: afterwards there is no way to tell which rows this set.
     */
    public function down(): void
    {
        //
    }
};
