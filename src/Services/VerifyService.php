<?php

namespace Redesign\ETL\Services;

use Redesign\ETL\Models\MigrationTracker;
use Redesign\ETL\Processors\ProcessorInterface;
use Redesign\ETL\Processors\Trait\SourceDBTrait;
use Redesign\ETL\Verifier\AbstractVerifier;
use Symfony\Component\Console\Helper\ProgressBar;

class VerifyService extends AbstractETLService
{
    use SourceDBTrait;

    /** @var array<string,array<string,array<string>>> */
    private array $verifiers;
    private array $processors;

    public function __construct()
    {
        parent::__construct();
        $this->verifiers = config('redesign.verifiers');
        $this->processors = config('redesign.processors');
    }

    public function run(int $chunkSize = 1000): void
    {
        $this->chunkSize = $chunkSize;

        $section1 = $this->output->section();
        $section2 = $this->output->section();

        $progress = new ProgressBar($section1, count($this->verifiers));
        $progress->start();
        $progress->setFormat("Table en cours <comment>%message%</comment>: \n [%bar%] %percent:3s%%");

        foreach ($this->verifiers as $newTable => $verifier) {
            $progress->setMessage($newTable);

            /** @var AbstractVerifier $verifier */
            $verifier = new $verifier['class']($newTable, ...$verifier['args']);

            $processors = [];
            if (isset($this->processors[$newTable])) {
                $processors = $this->processors[$newTable];
            }

            $proc = [];
            foreach ($processors as $processor) {
                $proc[] = new $processor['class'](...$processor['args']);
            }

            $oldTable = $this->getOldTable($newTable);
            $query = $this->old->table($oldTable)->select()->inRandomOrder();

            $verifier->modifyQuery($query);
            $progress2 = new ProgressBar($section2, $query->count());
            $progress2->setFormat("Nombre de ligne: %current%/%max%\nNombre d'update: %numberTrack%\n[%bar%] %percent%%");

            $tracker = 0;
            $query->chunk($this->chunkSize, function ($rows) use (&$verifier, &$proc, &$newTable, &$progress2, &$tracker) {
                foreach ($rows as $row) {
                    /**
                     * @var ProcessorInterface $processor *
                     */
                    $row = (array) $row;
                    foreach ($proc as $processor) {
                        $processor->process($row);
                    }
                    if (!$verifier->compare($row)) {
                        $this->trackData($row, $newTable);
                        ++$tracker;
                    }
                    $progress2->setMessage($tracker, 'numberTrack');
                    $progress2->advance();
                }
            });

            $progress->advance();
        }
    }

    private function getOldTable(string $newTable): string
    {
        return array_flip($this->tables)[$newTable];
    }

    private function trackData(array $row, string $newTable): void
    {
        $tracker = MigrationTracker::firstOrNew(
            ['table' => $newTable],
        );
        $tracker->addData($row)->save();
    }
}
