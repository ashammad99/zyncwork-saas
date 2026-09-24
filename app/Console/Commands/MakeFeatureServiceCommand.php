<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFeatureServiceCommand extends Command
{
    protected $signature = 'make:feature:service {feature : Feature name} {path* : [Role] [Name] (Name defaults to Service)}';
    protected $description = 'Create a service inside a feature';

    public function handle(): void
    {
        $feature = $this->argument('feature');
        $path    = $this->argument('path');

        // Resolve name and role from path
        $name = Str::studly(array_pop($path));
        $name = Str::endsWith($name, 'Service') ? $name : $name . 'Service';

        $rolePath  = !empty($path) ? implode('/', $path) : '';
        $fullPath  = !empty($rolePath) ? "{$feature}/{$rolePath}" : $feature;

        $parts     = array_map(fn($p) => Str::studly($p), explode('/', $fullPath));
        $namespace = 'App\\Features\\' . implode('\\', $parts) . '\\Services';
        $filePath  = app_path('Features/' . implode('/', $parts) . "/Services/{$name}.php");

        if (File::exists($filePath)) {
            $this->error("Service [{$name}] already exists!");
            return;
        }

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->stub($namespace, $name));

        $this->info("✅ Service [{$name}] created at app/Features/{$fullPath}/Services/{$name}.php");
    }

    private function stub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use App\Core\BaseService;

class {$name} extends BaseService
{
    //
}
PHP;
    }
}