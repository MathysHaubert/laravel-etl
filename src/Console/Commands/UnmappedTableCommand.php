<?php

namespace Redesign\ETL\Console\Commands;

use Illuminate\Database\Console\Migrations\BaseCommand;
use Redesign\ETL\Processors\Trait\SourceDBTrait;

class UnmappedTableCommand extends BaseCommand
{
    use SourceDBTrait;

    protected $signature = 'redesign:unmapped';
    protected $description = 'Run seeders';

    public function handle(): void
    {
        $this->connect();

        $configTable = array_keys(config('redesign.tables'));
        $tables = collect($this->sourceDB->select('SHOW TABLES'))
            ->map(function ($table) {
                return array_values((array) $table)[0]; // récupère la valeur sans connaître la clé
            })
            ->toArray()
        ;

        foreach (array_diff($tables, $configTable) as $table) {
            $this->output->writeln('<comment>La table <info>'.$table.'</info> est manquante dans la configuration</comment>');
        }
    }
}
