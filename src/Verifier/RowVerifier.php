<?php

namespace Redesign\ETL\Verifier;

class RowVerifier extends AbstractVerifier
{
    public function compare(array &$row): bool
    {
        $criteria = array_intersect_key($row, array_flip($this->columns));

        return $this->new
            ->table($this->table)
            ->where($criteria)
            ->exists()
        ;
    }
}
