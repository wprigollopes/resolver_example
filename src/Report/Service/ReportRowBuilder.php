<?php

declare(strict_types=1);

namespace App\Report\Service;

use App\Report\Enum\ReportIdentifier;

/**
 * ReportRowBuilder — orchestrates the column resolvers to produce one row.
 *
 * This is the "engine" that ties everything together:
 *   1. Receives a ReportIdentifier (which report type?)
 *   2. Asks the factory for the column definitions
 *   3. Iterates columns IN ORDER, resolving each one
 *   4. Feeds resolved values back into context (so later columns can use them)
 *   5. Returns the flat associative array (one row)
 *
 * The key subtlety is step 4: each resolved value is added to $context
 * BEFORE the next column is resolved. This is what enables ComputedResolver
 * to reference earlier columns (e.g., 'tax' can read 'total').
 *
 * TODO (Alejandro): Implement the buildRow() method.
 *
 * Hints:
 *   - Get columns from the factory: $this->templateFactory->columnsFor($report)
 *   - Loop over each ColumnDefinition
 *   - For each column, call $column->resolver->resolve($context)
 *   - Store the result in BOTH $row and $context (why both?)
 *     $row is the output; $context feeds into later resolvers
 *   - Return $row at the end
 *
 * Think about: Why do we merge resolved values into $context?
 * What would break if we didn't?
 */
class ReportRowBuilder
{
    public function __construct(
        private readonly ReportTemplateFactory $templateFactory,
    ) {}

    /**
     * Build a single report row.
     *
     * @param ReportIdentifier     $report  Which report template to use
     * @param array<string, mixed> $context The input data for this row
     *
     * @return array<string, mixed> The resolved row (column_name => value)
     */
    public function buildRow(ReportIdentifier $report, array $context): array
    {
        $columns = $this->templateFactory->columnsFor($report);
        foreach ($columns as $column) {
            $value = $column->resolver->resolve($context);
            $context[$column->name] = $value; // Add to context for dependent columns
            $row[$column->name] = $value;     // Add to output row
        }
        $row = [];

        // TODO: Iterate over $columns.
        //       For each ColumnDefinition:
        //         1. Resolve the value: $column->resolver->resolve($context)
        //         2. Store in $row[$column->name]
        //         3. Also store in $context[$column->name] ← critical for dependent columns
        //       Return $row.

        return $row;
    }

    /**
     * Build rows for a batch of items sharing the same report type.
     *
     * This is a convenience method for the common case of exporting
     * an entire dataset. It just loops buildRow().
     *
     * TODO (Alejandro): Implement this method.
     *
     * Hints:
     *   - Loop over $items, call buildRow() for each, collect results.
     *   - This is straightforward — 3-4 lines.
     *
     * Bonus: How would you add error handling here? If one row fails,
     * should the whole batch fail or should you skip and log?
     *
     * @param ReportIdentifier       $report  Which report template
     * @param array<int, array<string, mixed>> $items   Array of context arrays
     *
     * @return array<int, array<string, mixed>> Array of resolved rows
     */
    public function buildRows(ReportIdentifier $report, array $items): array
    {
        foreach ($items as $item) {
            $rows[] = $this->buildRow($report, $item);
        }
        return $rows;
    }
}
