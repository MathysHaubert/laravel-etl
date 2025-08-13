<?php

namespace Redesign\ETL\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Laravel\Prompts\Output\ConsoleOutput;

abstract class AbstractETLService
{
    /** Sortie console */
    protected ConsoleOutput $output;

    /** @var Connection (source) */
    protected Connection $old;

    /** @var Connection (destination) */
    protected Connection $new;

    /** Taille d’un bloc pour le hash segmenté */
    protected int $chunkSize = 1000;

    /** @var array<string,string> */
    protected array $tables;

    public function __construct()
    {
        $this->new = DB::connection('mysql');   // cible
        $this->old = DB::connection('old');     // source
        $this->output = new ConsoleOutput();
        $this->tables = config('redesign.tables', []);
    }
}
