<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFeatureExceptionCommand extends Command
{
    protected $signature = 'make:feature:exception {feature : Feature name} {path* : [Role] [Name] (Name defaults to Exception)}';
    protected $description = 'Create an exception inside a feature';

    public function handle(): void
    {
        $feature = $this->argument('feature');
        $path    = $this->argument('path');

        // Resolve name and role from path
        $name = Str::studly(array_pop($path));
        $name = Str::endsWith($name, 'Exception') ? $name : $name . 'Exception';

        $rolePath  = !empty($path) ? implode('/', $path) : '';
        $fullPath  = !empty($rolePath) ? "{$feature}/{$rolePath}" : $feature;

        $parts     = array_map(fn($p) => Str::studly($p), explode('/', $fullPath));
        $namespace = 'App\\Features\\' . implode('\\', $parts) . '\\Exceptions';
        $filePath  = app_path('Features/' . implode('/', $parts) . "/Exceptions/{$name}.php");

        if (File::exists($filePath)) {
            $this->error("Exception [{$name}] already exists!");
            return;
        }

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->stub($namespace, $name));

        $this->info("✅ Exception [{$name}] created at app/Features/{$fullPath}/Exceptions/{$name}.php");
    }

    private function stub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use App\Core\Exceptions\BaseException;

class {$name} extends BaseException
{
    //
}
PHP;
    }
}