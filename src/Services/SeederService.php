<?php

namespace Redesign\ETL\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Redesign\ETL\Seeders\SeederInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class SeederService extends AbstractETLService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(int $chunksize): void
    {
        $this->chunkSize = $chunksize;

        $this->new->disableQueryLog();
        $this->old->disableQueryLog();
        $this->new->statement('SET FOREIGN_KEY_CHECKS = 0');
        $this->new->statement('SET unique_checks = 0, foreign_key_checks = 0');

        $this->process();
        $this->output->writeln('');

        $this->new->statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->new->statement('SET unique_checks = 1, foreign_key_checks = 1');
        $this->new->enableQueryLog();
        $this->old->enableQueryLog();
    }

    private function process()
    {
        $seeders = config('redesign.seeders');
        $progress = new ProgressBar($this->output);

        /** @var SeederInterface $seeder */
        foreach ($seeders as $target => $seeder) {
            $seeder = new $seeder['class'](...$seeder['args']);
            $query = $this->old->table($seeder::SOURCE)->orderBy('id');
            $seeder->modifyQuery($query);

            $progress->start($query->count());
            $progress->setMessage($target);
            $progress->setFormat("Seed de <info>%message%</info>:\n %current%/%max%:[%bar%] %percent:3s%% | %estimated%");
            $progress->display();

            DB::beginTransaction();

            try {
                $query->chunk($this->chunkSize, function (Collection $rows) use (&$progress, &$seeder, &$target) {
                    $arrayRows = [];

                    $rows->map(function ($row) use (&$seeder, &$progress, &$arrayRows) {
                        $progress->advance();
                        $row = (array) $row;
                        $seeder->seed($row);
                        $arrayRows[] = $row;
                    });

                    $this->new->table($target)->upsert($arrayRows, $seeder->uniqueBy());

                    unset($rows);
                    gc_collect_cycles();
                });

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                dd($e);
            }
        }
    }
}
