<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module {name : The name of the module}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Domain-Driven Design (DDD) module structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $modulePath = base_path("Modules/{$name}");

        if (File::exists($modulePath)) {
            $this->error("Module {$name} already exists!");
            return Command::FAILURE;
        }

        $this->info("Creating module: {$name}...");

        // Define folder structure
        $directories = [
            'Domain/Entities',
            'Domain/ValueObjects',
            'Domain/Events',
            'Domain/Repositories',
            'Application/Commands',
            'Application/Queries',
            'Application/Services',
            'Infrastructure/Persistence/Migrations',
            'Infrastructure/Integrations',
            'Infrastructure/Providers',
            'Http/Controllers',
            'Http/Requests',
            'Http/Resources',
            'Tests',
        ];

        foreach ($directories as $dir) {
            $path = "{$modulePath}/{$dir}";
            File::makeDirectory($path, 0755, true, true);
            File::put("{$path}/.gitkeep", '');
        }

        // Generate files
        $this->createServiceProvider($name, $modulePath);
        $this->createRoutes($name, $modulePath);
        $this->createController($name, $modulePath);

        $this->info("Module {$name} created successfully!");
        $this->info("Please run 'composer dump-autoload' to ensure the module classes are recognized.");

        return Command::SUCCESS;
    }

    /**
     * Create the module's service provider.
     */
    protected function createServiceProvider(string $name, string $modulePath): void
    {
        $providerTemplate = <<<PHP
<?php

namespace Modules\\{$name}\\Infrastructure\\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class {$name}ServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        \$this->registerRoutes();
        \$this->registerMigrations();
    }

    /**
     * Register the module routes.
     */
    protected function registerRoutes(): void
    {
        \$routeFile = __DIR__ . '/../../Http/routes.php';

        if (file_exists(\$routeFile)) {
            Route::middleware('api')
                ->prefix('api/v1/' . strtolower('{$name}'))
                ->group(\$routeFile);
        }
    }

    /**
     * Register the module migrations.
     */
    protected function registerMigrations(): void
    {
        \$migrationsPath = __DIR__ . '/../Persistence/Migrations';

        if (is_dir(\$migrationsPath)) {
            \$this->loadMigrationsFrom(\$migrationsPath);
        }
    }
}
PHP;

        $providerPath = "{$modulePath}/Infrastructure/Providers/{$name}ServiceProvider.php";
        File::put($providerPath, $providerTemplate);
    }

    /**
     * Create the module routes file.
     */
    protected function createRoutes(string $name, string $modulePath): void
    {
        $routesTemplate = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use Modules\\{$name}\\Http\\Controllers\\{$name}Controller;

Route::get('/health', [{$name}Controller::class, 'health']);
PHP;

        $routesPath = "{$modulePath}/Http/routes.php";
        File::put($routesPath, $routesTemplate);
    }

    /**
     * Create the module test controller.
     */
    protected function createController(string $name, string $modulePath): void
    {
        $controllerTemplate = <<<PHP
<?php

namespace Modules\\{$name}\\Http\\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class {$name}Controller extends Controller
{
    /**
     * Check health of the module.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'module' => '{$name}',
            'message' => 'Module {$name} is functioning correctly.'
        ]);
    }
}
PHP;

        $controllerPath = "{$modulePath}/Http/Controllers/{$name}Controller.php";
        File::put($controllerPath, $controllerTemplate);
    }
}
