<?php

namespace Redesign\ETL\Console\Commands;

use Illuminate\Console\Command;
use Redesign\ETL\Services\MigrationService;
use Redesign\ETL\Services\SeederService;

class SeederCommand extends Command
{
    protected $signature = 'redesign:seed {--chunk-size=1000}';
    protected $description = 'Run seeders';

    public function handle(SeederService $service): void
    {
        $this->info('Début de la plantation...');
        $service->run($this->option('chunk-size'));
        $this->info('\nPlantation complète.');
    }
}
