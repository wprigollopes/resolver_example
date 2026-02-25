<?php

declare(strict_types=1);

namespace App\Report\Resolver;

/**
 * ContextValueResolver — extracts a value from the $context array by key.
 *
 * Use case: columns whose value already exists in the row data passed in.
 * For example, if the caller provides ['period' => '2026-Q1', 'sku' => 'ABC-123'],
 * a ContextValueResolver('period') will pull '2026-Q1' from that array.
 *
 * This avoids duplicating data — the value was already fetched upstream,
 * we just need to map it to the right column name.
 *
 * TODO (Alejandro): Implement the resolve() method.
 *
 * Hints:
 *   - The constructor receives $key (the array key to look up in $context).
 *   - If the key doesn't exist in $context, return null (don't throw).
 *   - This should be ~1 line of code. Think about the null coalescing operator (??).
 *
 * Bonus question: When would you want to throw an exception instead of
 * returning null? What are the trade-offs of silent failure vs. loud failure?
 */
class ContextValueResolver implements ColumnResolver
{
    public function __construct(
        private readonly string $key,
    ) {}

    public function resolve(array $context): mixed
    {
        return $context['name'] ?? null; // Placeholder implementation
        // TODO: Look up $this->key in the $context array.
        //       Return null if the key doesn't exist.
    }
}
