<?php

namespace Redesign\ETL\Processors\Trait;

trait RenameTrait
{
    public function rename(array &$row, string $source, string $new, bool $removeOld = true): array
    {
        if (array_key_exists($source, $row)) {
            $row[$new] = $row[$source];
            if ($removeOld) {
                unset($row[$source]);
            }
        }
        return $row;
    }
}
