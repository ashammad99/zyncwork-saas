<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFeatureObserverCommand extends Command
{
    protected $signature = 'make:feature:observer {feature : Feature name} {path* : [Role] [Name] (Name defaults to Observer)}';
    protected $description = 'Create an observer inside a feature';

    public function handle(): void
    {
        $feature = $this->argument('feature');
        $path    = $this->argument('path');

        // Resolve name and role from path
        $name = Str::studly(array_pop($path));
        $name = Str::endsWith($name, 'Observer') ? $name : $name . 'Observer';

        $rolePath  = !empty($path) ? implode('/', $path) : '';
        $fullPath  = !empty($rolePath) ? "{$feature}/{$rolePath}" : $feature;

        $parts     = array_map(fn($p) => Str::studly($p), explode('/', $fullPath));
        $namespace = 'App\\Features\\' . implode('\\', $parts) . '\\Observers';
        $filePath  = app_path('Features/' . implode('/', $parts) . "/Observers/{$name}.php");

        if (File::exists($filePath)) {
            $this->error("Observer [{$name}] already exists!");
            return;
        }

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->stub($namespace, $name));

        $this->info("✅ Observer [{$name}] created at app/Features/{$fullPath}/Observers/{$name}.php");
    }

    private function stub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Model;

class {$name}
{
    public function creating(Model \$model): void {}
    public function created(Model \$model): void {}
    public function updating(Model \$model): void {}
    public function updated(Model \$model): void {}
    public function deleting(Model \$model): void {}
    public function deleted(Model \$model): void {}
}
PHP;
    }
}