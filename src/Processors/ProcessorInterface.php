<?php

namespace Redesign\ETL\Processors;

interface ProcessorInterface
{
    /**
     * @param array<string,mixed> $row
     *                                 Prend en paramètre une ligne, pour la traiter avant de la remettre dans la rendre
     *
     * @return array
     */
    public function process(array &$row): void;
}
