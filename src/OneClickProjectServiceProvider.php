<?php

namespace IslamWalied\OneClickProject;

use Illuminate\Support\ServiceProvider;

class OneClickProjectServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                OneClickProject::class,
            ]);
        }
        $this->publishes([
            '../'. __DIR__.'/Traits' => app_path('Traits'),
        ], 'traits');

        // Publish helpers
        $this->publishes([
            '../'.__DIR__.'/Helpers' => app_path('Helpers'),
        ], 'helpers');
    }

    public function register()
    {
        // Register helpers
        require_once __DIR__ . '/Helpers/route_helper.php';
    }
}