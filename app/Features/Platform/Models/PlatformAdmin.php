<?php

namespace App\Features\Platform\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Zyncwork staff account for the platform console.
 *
 * Not a tenant user: its own table, guard ('platform'), host and session.
 * Signing in never sets a tenant, and the tenant login/password reset can
 * never find these accounts. Created only with `php artisan platform:admin`.
 */
class PlatformAdmin extends Authenticatable
{
    public const VIEW_SYSTEM_HEALTH = 'view_system_health';

    public const VIEW_BILLING = 'view_billing';

    public const SUSPEND_TENANTS = 'suspend_tenants';

    public const IMPERSONATE = 'impersonate';

    /** The complete list of platform powers; there are deliberately no others. */
    public const CAPABILITIES = [
        self::VIEW_SYSTEM_HEALTH,
        self::VIEW_BILLING,
        self::SUSPEND_TENANTS,
        self::IMPERSONATE,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'capabilities',
        'is_active',
    ];

    // two_factor_* are not fillable: only PlatformTwoFactorService and the CLI set them.
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    // No "remember me" for platform sessions, so no remember_token column.
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'capabilities' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function hasCapability(string $capability): bool
    {
        return $this->is_active && in_array($capability, $this->capabilities ?? [], true);
    }

    /**
     * Stored in the session at sign-in (RequirePlatformTwoFactor): a new
     * password changes it, which ends every session opened before. Keyed,
     * so the session never holds the password hash itself.
     */
    public function passwordFingerprint(): string
    {
        return hash_hmac('sha256', (string) $this->password, (string) config('app.key'));
    }
}
