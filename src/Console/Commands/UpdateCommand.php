<?php

namespace Redesign\ETL\Console\Commands;

use Illuminate\Console\Command;
use Redesign\ETL\Services\MigrationService;
use Redesign\ETL\Services\UpdateService;

class UpdateCommand extends Command
{
    protected $signature = 'redesign:update';
    protected $description = 'Update data';

    public function handle(UpdateService $service): void
    {
        $this->info("Début de l'update...");
        $service->run();
        $this->info('Update complète.');
    }
}
