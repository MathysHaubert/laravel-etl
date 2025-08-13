<?php

namespace Redesign\ETL\Services;

use Illuminate\Support\Facades\DB;
use Redesign\ETL\Models\MigrationTracker;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class UpdateService extends AbstractETLService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $tables = $this->new->table('migration_tracker')->get();

        $progress = new ProgressBar(new ConsoleOutput(), count($tables));

        foreach ($tables as $table) {
            $table = (array) $table;
            $table['data'] = json_decode($table['data'], true);
            $this->insert(...$table);
            $progress->advance();
        }
        $progress->finish();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function insert(int $id, string $table, array $data): void
    {
        foreach ($data as $row) {
            $pks = $this->getPrimaryKey($table);
            $this->new->table($table)->upsert($row, $pks);
        }
        MigrationTracker::destroy($id);
    }

    private function getPrimaryKey(string $table): array
    {
        $database = $this->new->getDatabaseName();

        return $this->new
            ->table('information_schema.KEY_COLUMN_USAGE')
            ->select('COLUMN_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', 'PRIMARY')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->toArray()
        ;
    }
}
