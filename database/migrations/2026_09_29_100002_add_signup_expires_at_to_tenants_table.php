<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when a tenant is created by public signup and cleared when its admin
     * first verifies. If it passes first, the signup is released (the command
     * onboarding:release-unverified-signups), so an unverified signup can't
     * hold on to someone else's email address.
     *
     * A tenant marker rather than the user's email_verified_at alone: that is
     * also null for an established user who has just changed their email, and
     * those must never be released.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('signup_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['signup_expires_at']);
            $table->dropColumn('signup_expires_at');
        });
    }
};
