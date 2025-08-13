<?php

namespace Redesign\ETL\Processors\Trait;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

trait SourceDBTrait
{
    private Connection $sourceDB;

    public function connect(): void
    {
        $this->sourceDB = DB::connection('old');
    }
}
