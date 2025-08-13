<?php

namespace Redesign\ETL\Processors;

readonly class RemoveMinusProcessor implements ProcessorInterface
{
    public function __construct(private array $attributes)
    {
    }


    /**
     * @inheritDoc
     */
    public function process(array &$row): void
    {
        foreach ($this->attributes as $attribute) {
            if (array_key_exists($attribute, $row)) {
                $row[$attribute] = ltrim($row[$attribute], "-");
            }
        }
    }
}
