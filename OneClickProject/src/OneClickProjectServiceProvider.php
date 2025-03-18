<?php

namespace IslamWalied\OneClickProject;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use IslamWalied\OneClickProject\Commands\ExportPostmanCollection;
use IslamWalied\OneClickProject\Commands\OneClickProject;

class OneClickProjectServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                OneClickProject::class,
//                ExportPostmanCollection::class,
            ]);

            $this->publishes([
                __DIR__ . '/Helpers' => app_path('Helpers'),
            ], 'helpers');

            $this->publishes([
                __DIR__ . '/Traits' => app_path('Traits'),
            ], 'traits');

            $this->publishes([
                __DIR__ . '/Middleware' => app_path('Http/Middleware'),
            ], 'middleware');

            $this->publishes([
                __DIR__ . '/Logging' => app_path('Logging'),
            ], 'logging');
        }
    }
}