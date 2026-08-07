<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $modulesPath = base_path('Modules');

        if (!is_dir($modulesPath)) {
            return;
        }

        // Scan the Modules directory for directories
        $modules = array_filter(scandir($modulesPath), function ($item) use ($modulesPath) {
            return is_dir($modulesPath . '/' . $item) && !in_array($item, ['.', '..']);
        });

        foreach ($modules as $module) {
            $providerClass = "Modules\\{$module}\\Infrastructure\\Providers\\{$module}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
