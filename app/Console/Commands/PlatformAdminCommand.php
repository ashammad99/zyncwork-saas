<?php

namespace App\Console\Commands;

use App\Features\Platform\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The only way to create a platform admin, change its capabilities,
 * reset its password or deactivate it: there is no signup or reset page.
 */
class PlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin
        {email : The platform admin\'s email}
        {--name= : Display name (required when creating)}
        {--capabilities= : Comma-separated: '.self::CAPABILITY_LIST.', or "all"}
        {--reset-password : Generate a new password}
        {--deactivate : Block this account from signing in}
        {--activate : Allow this account to sign in again}';

    protected $description = 'Create or update a platform (Zyncwork staff) admin';

    private const CAPABILITY_LIST = 'view_system_health,view_billing,suspend_tenants,impersonate';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $admin = PlatformAdmin::where('email', $email)->first();
        $creating = $admin === null;

        if ($creating && ! $this->option('name')) {
            $this->error('--name is required when creating a platform admin.');

            return self::FAILURE;
        }

        $admin ??= new PlatformAdmin(['email' => $email, 'is_active' => true, 'capabilities' => []]);

        if ($this->option('name')) {
            $admin->name = $this->option('name');
        }

        if ($this->option('capabilities') !== null) {
            $capabilities = $this->parseCapabilities($this->option('capabilities'));

            if ($capabilities === null) {
                return self::FAILURE;
            }

            $admin->capabilities = $capabilities;
        }

        if ($this->option('deactivate')) {
            $admin->is_active = false;
        } elseif ($this->option('activate')) {
            $admin->is_active = true;
        }

        $password = null;
        if ($creating || $this->option('reset-password')) {
            $password = Str::password(24);
            $admin->password = $password;
        }

        $admin->save();

        $this->info(($creating ? 'Created' : 'Updated')." platform admin {$admin->email}.");
        $this->line('Capabilities: '.(implode(', ', $admin->capabilities ?? []) ?: '(none)'));
        $this->line('Active: '.($admin->is_active ? 'yes' : 'no'));

        if ($password) {
            $this->warn("Password (shown once): {$password}");
        }

        return self::SUCCESS;
    }

    /** @return list<string>|null */
    private function parseCapabilities(string $value): ?array
    {
        if (trim($value) === 'all') {
            return PlatformAdmin::CAPABILITIES;
        }

        $capabilities = array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
        $unknown = array_diff($capabilities, PlatformAdmin::CAPABILITIES);

        if ($unknown) {
            $this->error('Unknown capabilities: '.implode(', ', $unknown));

            return null;
        }

        return $capabilities;
    }
}
