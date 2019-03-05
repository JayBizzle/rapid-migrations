<?php

namespace JayBizzle\RapidMigrations;

use Illuminate\Support\ServiceProvider;

class RapidMigrationsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/rapid-migrations.php' => config_path('rapid-migrations.php'),
        ], 'rapid-migrations-config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rapid-migrations.php', 'rapid-migrations');
    }
}