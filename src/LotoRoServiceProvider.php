<?php

namespace LHDev\LotoRo;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use LHDev\LotoRo\Commands\FetchLotoRo;
use LHDev\LotoRo\Commands\ExportLotoRo;
use LHDev\LotoRo\Commands\InstallLotoRo;

class LotoRoServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge the default config
        $this->mergeConfigFrom(__DIR__.'/../config/lotoro.php', 'lotoro');
    }

    public function boot()
    {
        // Publish the config file
        $this->publishes([
            __DIR__.'/../config/lotoro.php' => config_path('lotoro.php'),
        ], 'loto-ro-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations')
        ], 'loto-ro-migrations');

        // Rename the migration file with the current date
        $this->renameMigrationFile();

        // Register commands
        if($this->app->runningInConsole()) {
            $this->commands([
                FetchLotoRo::class,
                ExportLotoRo::class,
                InstallLotoRo::class
            ]);
        }

        // Check user configuration for route registration
        if(config('lotoro.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        } else {
            // If user published routes, load them instead
            $routesPath = base_path('routes/lotoro_routes.php');
            if (file_exists($routesPath)) {
                $this->loadRoutesFrom($routesPath);
            }
        }
    }

    /**
     * Rename the migration file with the current date
     */
    private function renameMigrationFile()
    {
        $migrationPath = database_path('migrations');

        $oldMigrationName = '2025_01_29_212602_create_lotoro_tables.php';
        $newMigrationName = date('Y_m_d_His').'_create_lotoro_tables.php';

        // only if the user didn't publish the migration file already (he / she might have customized it)
        if(File::exists($migrationPath.'/'.$oldMigrationName) && !File::exists($migrationPath.'/'.$newMigrationName)) {
            File::move($migrationPath.'/'.$oldMigrationName, $migrationPath.'/'.$newMigrationName);
        }
    }
}
