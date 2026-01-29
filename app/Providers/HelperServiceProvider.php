<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class HelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Load clinic database configuration
        $configPath = base_path('config/clinic_database.php');
        if (file_exists($configPath)) {
            require_once $configPath;
        }
        
        // Load service functions
        $services = [
            base_path('app/Services/clinic_functions.php'),
            base_path('app/Services/company_functions.php'),
        ];
        
        foreach ($services as $service) {
            if (file_exists($service)) {
                require_once $service;
            }
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
