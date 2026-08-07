<?php

namespace Modules\Tenancy\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class TenancyServiceProvider extends ServiceProvider
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
        $this->registerRoutes();
        $this->registerMigrations();
    }

    /**
     * Register the module routes.
     */
    protected function registerRoutes(): void
    {
        $routeFile = __DIR__ . '/../../Http/routes.php';

        if (file_exists($routeFile)) {
            Route::middleware('api')
                ->prefix('api/v1/' . strtolower('Tenancy'))
                ->group($routeFile);
        }
    }

    /**
     * Register the module migrations.
     */
    protected function registerMigrations(): void
    {
        $migrationsPath = __DIR__ . '/../Persistence/Migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }
}