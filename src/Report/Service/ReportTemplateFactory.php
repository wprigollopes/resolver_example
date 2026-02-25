<?php

declare(strict_types=1);

namespace App\Report\Service;

use App\Report\Enum\ReportIdentifier;
use App\Report\Model\ColumnDefinition;
use App\Report\Resolver\ComputedResolver;
use App\Report\Resolver\ContextValueResolver;
use App\Report\Resolver\DbLookupResolver;
use App\Report\Resolver\FixedValueResolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ReportTemplateFactory — builds the column list for a given report identifier.
 *
 * This factory exists to solve the DI problem with enums:
 *   - Enums can't have constructor-injected services.
 *   - DbLookupResolver needs EntityManagerInterface.
 *   - So this factory receives the EntityManager via Symfony's autowiring,
 *     and uses it when constructing resolvers that need it.
 *
 * Symfony's autoconfigure + autowire will automatically register this class
 * as a service (because it lives in src/ and services.yaml scans that directory).
 * Any controller or service can type-hint ReportTemplateFactory in its
 * constructor and Symfony will inject it automatically.
 *
 * PATTERN: This is a Factory Method pattern — the caller asks for a product
 * (column list) by providing an identifier, and the factory handles creation.
 *
 * @see https://refactoring.guru/design-patterns/factory-method
 */
class ReportTemplateFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Build the ordered list of ColumnDefinitions for a report type.
     *
     * TODO (Alejandro): Implement the INVENTORY_CHECK case (and your new case).
     *
     * For INVENTORY_CHECK, create columns:
     *   1. 'report_id'  → FixedValueResolver with value 'INVENTORY_CHECK'
     *   2. 'sku'        → ContextValueResolver pulling the 'sku' key
     *   3. 'warehouse'  → ContextValueResolver pulling 'warehouse'
     *   4. 'stock'      → DbLookupResolver (pick a DQL query that makes sense,
     *                      or use a placeholder string — it won't run without entities)
     *   5. 'reorder'    → ComputedResolver that returns true if stock < 10
     *
     * For your NEW case, design at least 3 columns using different resolver types.
     *
     * @return ColumnDefinition[]
     */
    public function columnsFor(ReportIdentifier $report): array
    {
        return match ($report) {

            // ── SALES_SUMMARY ──────────────────────────────────────
            // This one is fully implemented as a reference.
            // Notice the column order matters: 'total' must come before 'tax'
            // because the ComputedResolver for 'tax' reads $ctx['total'].
            ReportIdentifier::SALES_SUMMARY => [
                new ColumnDefinition(
                    'report_id',
                    new FixedValueResolver('SALES_SUMMARY'),
                ),
                new ColumnDefinition(
                    'period',
                    new ContextValueResolver('period'),
                ),
                new ColumnDefinition(
                    'total',
                    // In a real app this would query the DB.
                    // Using ContextValueResolver as a stand-in until entities exist.
                    new ContextValueResolver('total'),
                ),
                new ColumnDefinition(
                    'tax',
                    // Computed from the 'total' column.
                    // The 21% tax rate is hardcoded — in production you'd
                    // make this configurable (parameter, DB lookup, etc.)
                    new ComputedResolver(
                        fn(array $ctx): float => ($ctx['total'] ?? 0) * 0.21
                    ),
                ),
                new ColumnDefinition(
                    'net_total',
                    // Depends on both 'total' and 'tax' being resolved first.
                    // This is why column ORDER in this array matters!
                    new ComputedResolver(
                        fn(array $ctx): float => ($ctx['total'] ?? 0) - ($ctx['tax'] ?? 0)
                    ),
                ),
            ],

            // ── INVENTORY_CHECK ────────────────────────────────────
            // TODO (Alejandro): Define the columns for this report.
            //
            // Follow the pattern above. You'll need:
            //   - FixedValueResolver for the report identifier
            //   - ContextValueResolver for data that comes from the caller
            //   - ComputedResolver for derived values
            //
            // Remember: column order matters when a ComputedResolver
            // depends on a value from an earlier column.
            ReportIdentifier::INVENTORY_CHECK => [
                // Your implementation here...
            ],

            // TODO: Add your new report case here.
        };
    }
}
