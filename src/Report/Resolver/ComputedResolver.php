<?php

declare(strict_types=1);

namespace App\Report\Resolver;

/**
 * ComputedResolver — applies a formula (Closure) to the context to derive a value.
 *
 * Use case: columns that are calculated from other values. For example:
 *   - tax = total * 0.21
 *   - profit = revenue - costs
 *   - full_name = first_name . ' ' . last_name
 *
 * The Closure receives the entire $context array, so it can read any value
 * it needs — including values computed by earlier resolvers in the same row.
 *
 * Why a Closure and not a class?
 *   - For simple formulas (one-liners), creating a full class per formula
 *     would be overkill. Closures keep it concise.
 *   - For complex logic, you'd create a dedicated ColumnResolver class instead.
 *
 * TODO (Alejandro): Implement the resolve() method.
 *
 * Hints:
 *   - The constructor stores a Closure: fn(array $context): mixed
 *   - resolve() should invoke that Closure, passing $context as its argument.
 *   - This is ~1 line. Look up how to call a Closure stored in a property.
 *     (Tip: ($this->formula)($context) — the parentheses matter!)
 *
 * Think about: What happens if the Closure references a context key that
 * hasn't been computed yet? How does column order affect this?
 */
class ComputedResolver implements ColumnResolver
{
    public function __construct(
        private readonly \Closure $formula,
    ) {}

    public function resolve(array $context): mixed
    {
        // TODO: Invoke $this->formula with $context and return the result.
    }
}
