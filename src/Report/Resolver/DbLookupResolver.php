<?php

declare(strict_types=1);

namespace App\Report\Resolver;

use Doctrine\ORM\EntityManagerInterface;

/**
 * DbLookupResolver — fetches a value from the database using a DQL query or repository.
 *
 * Use case: columns whose values can't be pre-loaded and must be queried per-row.
 * For example: looking up the latest balance for a given account, or counting
 * related records.
 *
 * ---- IMPORTANT DESIGN DECISION ----
 *
 * This resolver receives an EntityManagerInterface (Doctrine's main entry point)
 * instead of a specific Repository. Why?
 *
 *   Option A: Inject a specific repository (e.g., SalesRepository)
 *     Pro: More focused, easier to mock in tests
 *     Con: You'd need a different DbLookupResolver subclass per entity
 *
 *   Option B: Inject EntityManagerInterface (this approach)
 *     Pro: One generic class, the DQL query can target any entity
 *     Con: Harder to test, less type-safe
 *
 *   Option C: Pass the repository through $context
 *     Pro: Keeps the resolver free of Symfony/Doctrine dependencies
 *     Con: Mixes infrastructure with data in the context array
 *
 * For this exercise we use Option B. In production code, Option A is often better
 * when you know your bounded contexts.
 *
 * TODO (Alejandro): Implement the resolve() method.
 *
 * Hints:
 *   - $this->dql contains a DQL query string, e.g.:
 *     'SELECT SUM(s.amount) FROM App\Entity\Sale s WHERE s.period = :period'
 *   - $this->paramKey is the context key to bind as a query parameter, e.g. 'period'
 *   - Steps:
 *     1. Create a query:  $this->entityManager->createQuery($this->dql)
 *     2. Set the parameter: ->setParameter($this->paramKey, $context[$this->paramKey])
 *     3. Get the result:  ->getSingleScalarResult()
 *   - Wrap the whole thing in a try/catch — if the query fails or returns
 *     no result, return null.
 *
 * Bonus: What performance problem could arise if this resolver is called
 * once per row in a report with 10,000 rows? (Hint: look up "N+1 query problem")
 */
class DbLookupResolver implements ColumnResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $dql,
        private readonly string $paramKey,
    ) {}

    public function resolve(array $context): mixed
    {
        try {
            if (!isset($context[$this->paramKey])) {
                return null; // Parameter key not found in context
            }
            $key = $context[$this->paramKey];

            if ($key = '12') {}
                $this->entityManager->createQuery($this->dql)
                    ->setParameter($this->paramKey, $key)
                    ->getSingleScalarResult();
        } catch (\Exception $e) {
            // Log the exception if needed (not implemented here)
            return null; // Return null on any failure  
            }
        // TODO: Execute the DQL query using $this->entityManager,
        //       binding $context[$this->paramKey] as the query parameter.
        //       Return the scalar result, or null on failure.
    }
}
