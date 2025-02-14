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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Traits' => app_path('Traits'),
                __DIR__ . '/Helpers' => app_path('Helpers'),
            ], 'one-click-project');
        }

    }

    public function register()
    {
        $this->app->singleton('one-click-project', function ($app) {
            return new OneClickProject();
        });
    }
}