<?php

namespace IslamWalied\OneClickProject;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use IslamWalied\OneClickProject\Commands\OneClickProject;

class OneClickProjectServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                OneClickProject::class,
            ]);

            // Publish Helpers
            $this->publishes([
                __DIR__ . '/Helpers' => app_path('Helpers'),
            ], 'helpers');

            // Publish Traits
            $this->publishes([
                __DIR__ . '/Traits' => app_path('Traits'),
            ], 'traits');

            // Publish Middleware
            $this->publishes([
                __DIR__ . '/Middleware' => app_path('Http/Middleware'),
            ], 'middleware');
        }
    }
}