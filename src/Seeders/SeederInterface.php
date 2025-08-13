<?php

namespace Redesign\ETL\Seeders;

use Illuminate\Database\Query\Builder;

interface SeederInterface
{
    public const SOURCE = "ORIGIN_TABLE"; // to override
    public function seed(array &$row): void;

    public function modifyQuery(Builder &$query): void;

    public function uniqueBy(): array;
}
