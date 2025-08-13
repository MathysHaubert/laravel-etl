<?php

namespace Redesign\ETL\Verifier;

use Illuminate\Database\Query\Builder;
use Redesign\ETL\Services\AbstractETLService;

abstract class AbstractVerifier extends AbstractETLService
{
    protected array $columns {
        get {
            return $this->columns;
        }
    }

    public function __construct(protected readonly string $table, ...$columns)
    {
        parent::__construct();
        $this->columns = $columns;
    }

    /**
     * @return bool true if row is same as base
     */
    abstract public function compare(array &$row): bool;

    public function modifyQuery(Builder &$query): void {}

}
