<?php

declare(strict_types=1);

namespace App\Report\Resolver;

/**
 * ColumnResolver — the core contract of the Strategy Pattern.
 *
 * Each implementation knows how to produce ONE column's value.
 * The $context array carries all the data a resolver might need:
 *   - row-level data (e.g. 'id', 'period', 'sku')
 *   - previously computed column values (for dependent calculations)
 *   - any shared lookup data
 *
 * By coding to this interface (not concrete classes), the ReportRowBuilder
 * can iterate over any mix of resolvers without knowing their internals.
 * This is the "O" in SOLID — Open for extension, Closed for modification.
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
interface ColumnResolver
{
    /**
     * Resolve the value for a single column.
     *
     * @param array<string, mixed> $context All available data for the current row
     *
     * @return mixed The computed column value
     */
    public function resolve(array $context): mixed;
}
