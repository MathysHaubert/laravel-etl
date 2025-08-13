<?php

namespace Redesign\ETL\Console\Commands;

use Illuminate\Database\Console\Migrations\BaseCommand;
use Redesign\ETL\Services\VerifyService;

class VerifyCommand extends BaseCommand
{
    protected $signature = 'redesign:verify';
    protected $description = 'Verify data corresponding to old base';

    public function handle(VerifyService $service): void
    {
        $this->info('Début de la vérification...');
        $service->run();
        $this->info('Vérification complète.');
    }
}
