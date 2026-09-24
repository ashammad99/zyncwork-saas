<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFeatureRequestCommand extends Command
{
    protected $signature = 'make:feature:request {feature : Feature name} {path* : [Role] [Name] (Name defaults to Request)}';
    protected $description = 'Create a form request inside a feature';

    public function handle(): void
    {
        $feature = $this->argument('feature');
        $path    = $this->argument('path');

        // Resolve name and role from path
        $name = Str::studly(array_pop($path));
        $name = Str::endsWith($name, 'Request') ? $name : $name . 'Request';

        $rolePath  = !empty($path) ? implode('/', $path) : '';
        $fullPath  = !empty($rolePath) ? "{$feature}/{$rolePath}" : $feature;

        $parts     = array_map(fn($p) => Str::studly($p), explode('/', $fullPath));
        $namespace = 'App\\Features\\' . implode('\\', $parts) . '\\Requests';
        $filePath  = app_path('Features/' . implode('/', $parts) . "/Requests/{$name}.php");

        if (File::exists($filePath)) {
            $this->error("Request [{$name}] already exists!");
            return;
        }

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->stub($namespace, $name));

        $this->info("✅ Request [{$name}] created at app/Features/{$fullPath}/Requests/{$name}.php");
    }

    private function stub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class {$name} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            //
        ];
    }
}
PHP;
    }
}