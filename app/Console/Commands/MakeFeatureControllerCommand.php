<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFeatureControllerCommand extends Command
{
    protected $signature = 'make:feature:controller {feature : Feature name} {path* : [Role] [Name] (Name defaults to Controller)}';
    protected $description = 'Create a controller inside a feature';

    public function handle(): void
    {
        $feature = $this->argument('feature');
        $path    = $this->argument('path');

        // Resolve name and role from path
        $name = Str::studly(array_pop($path));
        $name = Str::endsWith($name, 'Controller') ? $name : $name . 'Controller';

        $rolePath  = !empty($path) ? implode('/', $path) : '';
        $fullPath  = !empty($rolePath) ? "{$feature}/{$rolePath}" : $feature;

        $parts      = array_map(fn($p) => Str::studly($p), explode('/', $fullPath));
        $namespace  = 'App\\Features\\' . implode('\\', $parts) . '\\Controllers';
        $filePath   = app_path('Features/' . implode('/', $parts) . "/Controllers/{$name}.php");

        if (File::exists($filePath)) {
            $this->error("Controller [{$name}] already exists!");
            return;
        }

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->stub($namespace, $name));

        $this->info("✅ Controller [{$name}] created at app/Features/{$fullPath}/Controllers/{$name}.php");
    }

    private function stub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use App\Core\BaseController;

class {$name} extends BaseController
{
    //
}
PHP;
    }
}