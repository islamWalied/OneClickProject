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
    }

    public function register()
    {
        //
    }
}