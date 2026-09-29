<?php

namespace Database\Seeders;

use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed into the first tenant (creating "Default" on a fresh database).
        $tenant = Tenant::query()->oldest('id')->first() ?? Tenant::create(['name' => 'Default']);

        app(TenantContext::class)->run($tenant->id, fn () => $this->seedTenant());
    }

    private function seedTenant(): void
    {
        // User::factory(10)->create();

        User::factory()->createMany(
            [
                [
                    'name' => 'Employee user',
                    'email' => 'emp@example.com',
                    'password' => Hash::make('12345678'),
                    'role' => 'employee',
                    'is_active' => true,
                    'profile_image' => null,
                    'last_login_at' => null,
                    'last_login_ip' => null,
                ],
                [
                    'name' => 'Admin User',
                    'email' => 'admin@example.com',
                    'password' => Hash::make('12345678'),
                    'role' => 'admin',
                    'is_active' => true,
                    'profile_image' => null,
                    'last_login_at' => null,
                    'last_login_ip' => null,
                ],
                [
                    'name' => 'Manager User',
                    'email' => 'manager@example.com',
                    'password' => Hash::make('12345678'),
                    'role' => 'manager',
                    'is_active' => true,
                    'profile_image' => null,
                    'last_login_at' => null,
                    'last_login_ip' => null,
                ]
            ]
        );

        // Managers need a department_id or most project/task policies deny them.
        $manager = User::where('email', 'manager@example.com')->first();
        $department = Department::create([
            'name' => 'General',
            'manager_id' => $manager->id,
        ]);
        User::whereIn('email', ['manager@example.com', 'emp@example.com'])
            ->update(['department_id' => $department->id]);

        $this->call(ProjectSeeder::class);
    }
}
