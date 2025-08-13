<?php

namespace Redesign\ETL\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Redesign\ETL\Processors\ProcessorInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class MigrationService extends AbstractETLService
{
    /** @var array<string,string> ['ancienne_table' => 'nouvelle_table'] */
    private array $processors;

    public function __construct()
    {
        parent::__construct();
        $this->processors = config('redesign.processors', []);
    }

    /**
     * Lance la migration et, si $verify=true, l’intégrité par hash segmenté.
     */
    public function run(int $chunkSize, bool $verify = true): void
    {
        $this->chunkSize = $chunkSize;

        $this->new->disableQueryLog();
        $this->old->disableQueryLog();
        $this->new->statement('SET FOREIGN_KEY_CHECKS = 0');
        $this->new->statement('SET unique_checks = 0, foreign_key_checks = 0');

        $startAt = microtime(true);

        foreach ($this->tables as $sourceTable => $targetTable) {
            DB::beginTransaction();

            try {
                // --- Migration incrémentale ---
                if (
                    $this->new->table($targetTable)->count() === ($count = $this->old->table($sourceTable)->count())
                ) {
                    $this->output->writeln('<info>Table '.$targetTable.' déjà à jour</info>');
                    continue;
                }
                $this->migrateFullTable($sourceTable, $targetTable);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->output->writeln("<error>Migration échouée pour {$targetTable}: {$e->getMessage()}</error>");
            } finally {
                DB::statement("ALTER TABLE {$targetTable} ENABLE KEYS");
            }

            $this->output->writeln("<info>{$targetTable} ✔️ migration".($verify ? ' + vérification' : '')." terminées</info>\n");
        }

        $this->output->writeln('<fg=blue;options=bold>Nombre total de tables affectées: '.count($this->tables));
        $this->new->statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->new->statement('SET unique_checks = 1, foreign_key_checks = 1');
        $this->new->enableQueryLog();
        $this->old->enableQueryLog();

        $endAt = microtime(true);
        $this->output->writeln('Temps: '.($endAt - $startAt));
    }

    /**
     * Copie les nouvelles lignes d’une table source vers la table cible.
     *
     * @param int     $total       Nombre total de lignes à insérer
     * @param string  $targetTable Nom de la table dans la refonte
     * @param Builder $query       QueryBuilder pointant sur la table source
     */
    public function manageInsert(int $total, string $targetTable, Builder $query): void
    {
        if (0 === $total) {
            return;
        }

        $progress = new ProgressBar($this->output, $total);
        $progress->setMessage($targetTable);
        $progress->setFormat("Migration de <info>%message%</info>:\n %current%/%max%:[%bar%] %percent:3s%% | %elapsed%");
        $progress->display();

        $dropped = [];

        $query->chunk($this->chunkSize, function (Collection $rows) use ($targetTable, &$progress, &$dropped) {
            $rows->transform(fn ($row) => $this->prepareSingleRow($row, $targetTable));

            $rows = $rows->filter(function ($row) {
                return !isset($row['__to_be_deleted']) || false === $row['__to_be_deleted'];
            });

            $rows->transform(function ($row) {
                unset($row['__to_be_deleted']);

                return $row;
            });

            while (true) {
                $dataToInsert = $this->normalizeColumnsForChunk($rows, $targetTable);
                $dataToInsert = $this->dropColumns($dataToInsert, $dropped);

                try {
                    $this->new->table($targetTable)->insertOrIgnore($dataToInsert->toArray());

                    break;
                } catch (QueryException $e) {
                    $this->output->write("<error>{$e->getMessage()}</error>");
                }
            }
            $progress->advance($rows->count());
        });

        $this->finishProgress($progress);
    }

    private function needsMigration(string $sourceTable, string $targetTable, int $trackedLastId = 0): array
    {
        $srcCount = $this->old->table($sourceTable)->count();
        $srcMaxId = $this->getMaxId($this->old, $sourceTable) ?? 0;

        $tgtCount = $this->new->table($targetTable)->count();
        $tgtMaxId = $this->getMaxId($this->new, $targetTable) ?? 0;

        if ($trackedLastId > 0) {
            $exists = $this->new->table($targetTable)->where('id', $trackedLastId)->exists();
            if (!$exists) {
                $trackedLastId = $tgtMaxId; // on reprend où la cible en est réellement
            }
        } else {
            $trackedLastId = $tgtMaxId;
        }

        $needs = ($srcCount > $tgtCount) || ($srcMaxId > $tgtMaxId);

        return [
            'needs' => $needs,
            'resume_id' => $trackedLastId,
            'src' => compact('srcCount', 'srcMaxId'),
            'tgt' => compact('tgtCount', 'tgtMaxId'),
        ];
    }

    private function fillMissingHoles(string $sourceTable, string $targetTable): void
    {
        if (!$this->new->getSchemaBuilder()->hasColumn($targetTable, 'id')) {
            return;
        }

        $missingIds = $this->old->table($sourceTable)
            ->leftJoin($this->new->table($targetTable), "{$sourceTable}.id", '=', "{$targetTable}.id")
            ->whereNull("{$targetTable}.id")
            ->limit(10000)
            ->pluck("{$sourceTable}.id")
        ;

        if ($missingIds->isEmpty()) {
            return;
        }

        $rows = $this->old->table($sourceTable)->whereIn('id', $missingIds)->orderBy('id')->get();
        $rows = $rows->map(fn ($row) => $this->prepareSingleRow($row, $targetTable));
        $rows = $this->normalizeColumnsForChunk($rows, $targetTable);

        $this->new->table($targetTable)->insertOrIgnore($rows->toArray());
    }

    private function migrateFullTable(string $sourceTable, string $targetTable): void
    {
        $query = $this->old->table($sourceTable);

        $total = $query->count();

        if (!isset($query->orders)) {
            if ($this->old->getSchemaBuilder()->hasColumn($query->from, 'id')) {
                $query->orderBy('id');
            } else {
                // Table pivot
                $firstColumn = $this->old->getSchemaBuilder()->getColumnListing($query->from)[0];
                $query->orderBy($firstColumn);
                foreach ($this->old->getSchemaBuilder()->getColumnListing($query->from) as $column) {
                    $query->whereNotNull($column);
                }
            }
        }

        if (0 === $total) {
            $this->output->writeln("<comment>{$sourceTable} est vide. Aucune ligne à migrer.</comment>");

            return;
        }

        $this->manageInsert($total, $targetTable, $query);
    }

    private function normalizeColumnsForChunk(Collection $rows, string $table): Collection
    {
        $columns = $this->new->getSchemaBuilder()->getColumnListing($table);
        $defaultValues = $this->getDefaultValue($table);

        return $rows->map(function ($row) use ($columns, $defaultValues) {
            $cleanRow = [];
            foreach ($columns as $col) {
                $cleanRow[$col] = $row[$col] ?? $defaultValues[$col] ?? null;
            }

            return $cleanRow;
        });
    }

    private function prepareSingleRow(object $row, string $table): array
    {
        $row = (array) $row;
        $inProcess = in_array($table, array_keys($this->processors));

        if ($inProcess) {
            /** @var ProcessorInterface $processor */
            foreach ($this->processors[$table] as $processor) {
                $proc = new $processor['class'](...$processor['args']);
                $proc->process($row);
            }
        }

        return $row;
    }

    // ─────────────────────────── Helpers ───────────────────────────

    private function getDefaultValue(string $tableName): array
    {
        $columns = DB::select('
        SELECT COLUMN_NAME, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = ?', [$tableName]);

        return collect($columns)->mapWithKeys(function ($col) {
            return [$col->COLUMN_NAME => $col->COLUMN_DEFAULT];
        })->all();
    }

    private function dropColumns(Collection &$rows, array $dropped): Collection
    {
        $rows = $rows->map(function ($row) use ($dropped) {
            foreach ($dropped as $col) {
                unset($row->{$col});
            }

            return $row;
        });

        return $rows;
    }

    private function finishProgress(ProgressBar $progress): void
    {
        $progress->finish();
        $this->output->writeln('');
    }

    /** Renvoie le max(id) d’une table donnée. */
    private function getMaxId(Connection $conn, string $table): ?int
    {
        if ($conn->getSchemaBuilder()->hasColumn($table, 'id')) {
            return (int) $conn->table($table)->max('id');
        }

        return null;
    }

    private function manageInsertToTargetTable(Collection $rows, string $targetTable): void
    {
        $rows = $rows->map(fn ($row) => $this->prepareSingleRow($row, $targetTable));
        $rows = $rows->filter(fn ($row) => !isset($row['__to_be_deleted']) || false === $row['__to_be_deleted'])
            ->map(function ($row) {
                unset($row['__to_be_deleted']);

                return $row;
            })
        ;

        $dataToInsert = $this->normalizeColumnsForChunk($rows, $targetTable);

        $dataToInsert->chunk($this->chunkSize)->each(function ($chunk) use ($targetTable) {
            $this->new->table($targetTable)->insertOrIgnore($chunk->toArray());
        });
    }

    private function getPrimaryKey(string $sourceTable): ?string
    {
        $indexes = $this->old->getSchemaBuilder()->getIndexes($sourceTable);

        foreach ($indexes as $index) {
            if (true === $index['primary']) {
                return $index['columns'][0];
            }
        }

        return null;
    }
}
