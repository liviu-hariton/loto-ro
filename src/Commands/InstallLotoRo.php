<?php

namespace LHDev\LotoRo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallLotoRo extends Command
{
    protected $signature = 'lotoro:install';
    protected $description = 'Setup LotoRo package with configuration options';

    public function handle()
    {
        $this->info('LotoRo Package Installation');

        // Ask the user if he / she wants automatic route registration
        $registerRoutes = $this->confirm('Do you want to automatically register routes? If you chose "no", you\'ll have the option to publish them, next', false);

        // Ensure the config file is published before modifying it
        if(!File::exists(config_path('lotoro.php'))) {
            $this->callSilent('vendor:publish', ['--tag' => 'loto-ro-config']);
        }

        // Modify the config file
        $this->updateConfig('register_routes', $registerRoutes);

        // f auto-routes are disabled, offer to publish routes
        if(!$registerRoutes) {
            $publishRoutes = $this->confirm('Do you want to publish the routes for later customization?', true);

            if($publishRoutes) {
                $routesPath = base_path('routes/lotoro_routes.php');

                if(!File::exists($routesPath)) {
                    File::copy(__DIR__ . '/../../stubs/routes/lotoro_routes.stub', $routesPath);

                    $this->info('Routes published to routes/lotoro_routes.php.');
                } else {
                    $this->warn('Routes file already exists! Modify the routes/lotoro_routes.php file directly.');
                }
            } else {
                $this->info('No routes were registered or published. You will need to manually define them.');
            }
        }

        $this->info('LotoRo setup completed!');
    }

    /**
     * Update the config file with the user's choice.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function updateConfig(string $key, mixed $value)
    {
        $configFile = config_path('lotoro.php');

        if(File::exists($configFile)) {
            $configContent = File::get($configFile);

            $configContent = preg_replace(
                "/'$key'\s*=>\s*(true|false)/",
                "'$key' => " . ($value ? 'true' : 'false'),
                $configContent
            );

            File::put($configFile, $configContent);
        }
    }
}