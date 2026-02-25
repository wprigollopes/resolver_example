<?php

declare(strict_types=1);

namespace App\Report\Model;

use App\Report\Resolver\ColumnResolver;

/**
 * ColumnDefinition — pairs a column name with its resolver.
 *
 * This is a simple Value Object: it holds data but has no behavior of its own.
 * Using `readonly` properties ensures immutability — once created, a column
 * definition can't be accidentally modified.
 *
 * The "readonly" promoted constructor syntax (PHP 8.1+) keeps this concise.
 * Think of each ColumnDefinition as one cell's "recipe":
 *   - $name     → the column header (e.g. 'total', 'tax', 'sku')
 *   - $resolver → the strategy that computes the cell's value
 */
class ColumnDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly ColumnResolver $resolver,
    ) {}
}
