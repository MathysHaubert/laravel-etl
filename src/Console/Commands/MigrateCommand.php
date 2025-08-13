<?php

namespace Redesign\ETL\Console\Commands;

use Illuminate\Console\Command;
use Redesign\ETL\Services\MigrationService;

class MigrateCommand extends Command
{
    protected $signature = 'redesign:migrate {--chunk-size=1000}';
    protected $description = 'Run the data migration';

    public function handle(MigrationService $service): void
    {
        $this->info('Début de la migration...');
        $service->run($this->option('chunk-size'), false);
        $this->info('Migration complète.');
    }
}
