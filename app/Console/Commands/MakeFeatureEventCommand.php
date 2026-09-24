<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFeatureEventCommand extends Command
{
    protected $signature = 'make:feature:event {feature : Feature name} {path* : [Role] [Name]}';
    protected $description = 'Create an event inside a feature';

    public function handle(): void
    {
        $feature = $this->argument('feature');
        $path    = $this->argument('path');

        // Resolve name and role from path
        $name = Str::studly(array_pop($path));

        $rolePath  = !empty($path) ? implode('/', $path) : '';
        $fullPath  = !empty($rolePath) ? "{$feature}/{$rolePath}" : $feature;

        $parts     = array_map(fn($p) => Str::studly($p), explode('/', $fullPath));
        $namespace = 'App\\Features\\' . implode('\\', $parts) . '\\Events';
        $filePath  = app_path('Features/' . implode('/', $parts) . "/Events/{$name}.php");

        if (File::exists($filePath)) {
            $this->error("Event [{$name}] already exists!");
            return;
        }

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->stub($namespace, $name));

        $this->info("✅ Event [{$name}] created at app/Features/{$fullPath}/Events/{$name}.php");
    }

    private function stub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class {$name}
{
    use Dispatchable, SerializesModels;

    public function __construct()
    {
        //
    }
}
PHP;
    }
}