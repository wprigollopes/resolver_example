<?php

declare(strict_types=1);

namespace App\Report\Resolver;

/**
 * FixedValueResolver — returns a hardcoded value, ignoring context entirely.
 *
 * Use case: columns like 'report_id' or 'version' where the value
 * is always the same for every row of a given report type.
 *
 * This is the simplest possible resolver — a good example to read first
 * before tackling the more complex ones below.
 *
 * Example usage:
 *   new FixedValueResolver('SALES_SUMMARY')  → always returns 'SALES_SUMMARY'
 *   new FixedValueResolver(1)                → always returns 1
 */
class FixedValueResolver implements ColumnResolver
{
    public function __construct(
        private readonly mixed $value,
    ) {}

    public function resolve(array $context): mixed
    {
        // No computation needed — just return the fixed value.
        return $this->value;
    }
}
