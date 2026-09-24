<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFeatureCommand extends Command
{
    protected $signature = 'make:feature {name : Feature name e.g. Auth} {roles?* : Optional roles e.g. Admin User} {--roles= : Comma separated roles e.g. Admin,User}';

    protected $description = 'Create a new feature with full folder structure and .gitkeep files';

    private array $simpleFolders = [
        'Controllers',
        'Models',
        'Services',
        'Requests',
        'Observers',
        'Events',
        'Exceptions',
        'routes',
    ];

    public function handle(): void
    {
        $name  = Str::studly($this->argument('name'));
        $roles = $this->option('roles');
        $roleArgs = $this->argument('roles');

        // If roles option is not provided, use roles from arguments
        if (!$roles && !empty($roleArgs)) {
            $roles = implode(',', $roleArgs);
        }

        if ($roles) {
            $this->createRoleBasedFeature($name, $roles);
        } else {
            $this->createSimpleFeature($name);
        }
    }

    private function createSimpleFeature(string $name): void
    {
        $basePath = app_path("Features/{$name}");

        if (File::isDirectory($basePath)) {
            $this->error("Feature [{$name}] already exists!");
            return;
        }

        foreach ($this->simpleFolders as $folder) {
            $path = "{$basePath}/{$folder}";
            File::makeDirectory($path, 0755, true);
            File::put("{$path}/.gitkeep", '');
        }

        // Create empty route files
        File::put("{$basePath}/routes/web.php", $this->routeStub($name, 'web'));
        File::put("{$basePath}/routes/api.php", $this->routeStub($name, 'api'));

        $this->info("✅ Simple feature [{$name}] created at app/Features/{$name}");
        $this->table(['Folder'], array_map(fn($f) => [$f], $this->simpleFolders));
    }

    private function createRoleBasedFeature(string $name, string $roles): void
    {
        $basePath  = app_path("Features/{$name}");
        $roleList  = array_map(fn($r) => Str::studly(trim($r)), explode(',', $roles));

        if (File::isDirectory($basePath)) {
            $this->error("Feature [{$name}] already exists!");
            return;
        }

        // Create feature root .gitkeep
        File::makeDirectory($basePath, 0755, true);
        File::put("{$basePath}/.gitkeep", '');

        foreach ($roleList as $role) {
            $rolePath = "{$basePath}/{$role}";

            foreach ($this->simpleFolders as $folder) {
                $path = "{$rolePath}/{$folder}";
                File::makeDirectory($path, 0755, true);
                File::put("{$path}/.gitkeep", '');
            }

            // Create empty route files
            File::put("{$rolePath}/routes/web.php", $this->routeStub("{$name}/{$role}", 'web'));
            File::put("{$rolePath}/routes/api.php", $this->routeStub("{$name}/{$role}", 'api'));

            $this->info("  ✅ Role [{$role}] scaffolded");
        }

        $this->info("✅ Role-based feature [{$name}] created with roles: " . implode(', ', $roleList));
    }

    private function routeStub(string $name, string $type): string
    {
        return "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n// {$name} {$type} routes\n";
    }
}