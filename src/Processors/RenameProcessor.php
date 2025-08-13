<?php

namespace Redesign\ETL\Processors;

use Redesign\ETL\Processors\Trait\RenameTrait;

readonly class RenameProcessor implements ProcessorInterface
{
    use RenameTrait;

    public function __construct(private readonly string $sourceColumns, private readonly string $targetColumns)
    {
    }
    /**
     * @inheritDoc
     */
    public function process(array &$row): void
    {
        $this->rename($row, $this->sourceColumns, $this->targetColumns);
    }
}
