<?php

namespace Redesign\ETL\Providers;

use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Redesign\ETL\Console\Commands\MigrateCommand;
use Redesign\ETL\Console\Commands\SeederCommand;
use Redesign\ETL\Console\Commands\UnmappedTableCommand;
use Redesign\ETL\Console\Commands\UpdateCommand;
use Redesign\ETL\Console\Commands\VerifyCommand;
use Redesign\ETL\Services\MigrationService;
use Redesign\ETL\Services\SeederService;

class ETLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Enregistrement explicite des services nécessaires au CLI
        $this->app->register(ViewServiceProvider::class);
        $this->app->register(FilesystemServiceProvider::class);

        $this->mergeConfigFrom(
            dirname(__DIR__, 2).'/config/redesign.php',
            'redesign'
        );

        // Bind du service principal
        $this->app->singleton(MigrationService::class, function ($app) {
            return new MigrationService();
        });

        $this->app->singleton(SeederService::class, function ($app) {
            return new SeederService();
        });

        $this->app->singleton(VerifyCommand::class, function ($app) {
            return new VerifyCommand();
        });

        $this->app->singleton(UpdateCommand::class, function ($app) {
            return new UpdateCommand();
        });

        // Enregistrement de la commande
        $this->commands([
            MigrateCommand::class,
            SeederCommand::class,
            VerifyCommand::class,
            UnmappedTableCommand::class,
            UpdateCommand::class,
        ]);
    }

    public function boot(): void
    {
        // Chargement des vues du package
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gdd');
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__, 2).'/config/redesign.php' => config_path('redesign.php'),
            ], 'config');
        }

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/gdd'),
        ], 'views');

        $this->registerDatabaseConnections();
    }

    protected function registerDatabaseConnections(): void
    {
        $connections = config('redesign.connections', []);
        if (empty($connections)) {
            return;
        }

        $databaseConnections = config('database.connections', []);
        $merged = array_merge($databaseConnections, $connections);
        config(['database.connections' => $merged]);
    }
}
