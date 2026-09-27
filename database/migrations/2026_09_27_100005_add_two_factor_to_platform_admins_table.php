<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TOTP for platform admins (PlatformTwoFactorService). All nullable:
     * an admin without a confirmed secret is sent to setup at next sign-in.
     */
    public function up(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            // Encrypted with APP_KEY (model cast); written only once a code confirms it.
            $table->text('two_factor_secret')->nullable()->after('password');
            // JSON list of SHA-256 hashes; each code works once.
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            // Last accepted 30-second time step, so a code cannot be replayed.
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
