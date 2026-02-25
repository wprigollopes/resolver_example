<?php

declare(strict_types=1);

namespace App\Tests\Report;

use App\Report\Enum\ReportIdentifier;
use App\Report\Model\ColumnDefinition;
use App\Report\Resolver\ComputedResolver;
use App\Report\Resolver\ContextValueResolver;
use App\Report\Resolver\FixedValueResolver;
use App\Report\Service\ReportRowBuilder;
use App\Report\Service\ReportTemplateFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Report Row Builder system.
 *
 * These tests verify each resolver individually, then test them working
 * together through the ReportRowBuilder. This is a classic "unit → integration"
 * testing approach.
 *
 * Run with: php bin/phpunit tests/Report/ReportRowBuilderTest.php
 *
 * NOTE: These tests will FAIL until you implement the TODO methods.
 * That's intentional — they serve as your acceptance criteria.
 * Make them green one by one!
 */
class ReportRowBuilderTest extends TestCase
{
    // ── Individual Resolver Tests ──────────────────────────────

    public function testFixedValueResolverReturnsFixedValue(): void
    {
        $resolver = new FixedValueResolver('hello');

        // The context is irrelevant — a fixed resolver always returns its value.
        $this->assertSame('hello', $resolver->resolve([]));
        $this->assertSame('hello', $resolver->resolve(['any' => 'data']));
    }

    public function testFixedValueResolverWorksWithDifferentTypes(): void
    {
        $this->assertSame(42, (new FixedValueResolver(42))->resolve([]));
        $this->assertSame(true, (new FixedValueResolver(true))->resolve([]));
        $this->assertNull((new FixedValueResolver(null))->resolve([]));
    }

    public function testContextValueResolverExtractsKeyFromContext(): void
    {
        $resolver = new ContextValueResolver('name');

        $this->assertSame('Alejandro', $resolver->resolve(['name' => 'Alejandro']));
    }

    public function testContextValueResolverReturnsNullForMissingKey(): void
    {
        $resolver = new ContextValueResolver('missing_key');

        // Should NOT throw — just return null.
        $this->assertNull($resolver->resolve(['other' => 'data']));
    }

    public function testComputedResolverAppliesFormula(): void
    {
        $resolver = new ComputedResolver(
            fn(array $ctx): float => ($ctx['price'] ?? 0) * ($ctx['quantity'] ?? 0)
        );

        $result = $resolver->resolve(['price' => 10.0, 'quantity' => 3]);
        $this->assertSame(30.0, $result);
    }

    public function testComputedResolverCanAccessPreviouslyResolvedValues(): void
    {
        // This simulates a context that has been enriched by earlier resolvers.
        $resolver = new ComputedResolver(
            fn(array $ctx): float => ($ctx['total'] ?? 0) * 0.21
        );

        // 'total' was already resolved and added to context by a prior column.
        $result = $resolver->resolve(['total' => 100.0]);
        $this->assertEqualsWithDelta(21.0, $result, 0.001);
    }

    // ── Integration Test: Full Row Building ────────────────────

    public function testBuildRowProducesSalesSummary(): void
    {
        // We mock the EntityManager since we don't have a real DB in unit tests.
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $factory = new ReportTemplateFactory($entityManager);
        $builder = new ReportRowBuilder($factory);

        $context = [
            'period' => '2026-Q1',
            'total'  => 1000.0,
        ];

        $row = $builder->buildRow(ReportIdentifier::SALES_SUMMARY, $context);

        // Verify each column was resolved correctly.
        $this->assertSame('SALES_SUMMARY', $row['report_id']);
        $this->assertSame('2026-Q1', $row['period']);
        $this->assertSame(1000.0, $row['total']);
        $this->assertEqualsWithDelta(210.0, $row['tax'], 0.001);      // 1000 * 0.21
        $this->assertEqualsWithDelta(790.0, $row['net_total'], 0.001); // 1000 - 210
    }

    public function testBuildRowsProcessesMultipleItems(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $factory = new ReportTemplateFactory($entityManager);
        $builder = new ReportRowBuilder($factory);

        $items = [
            ['period' => '2026-Q1', 'total' => 500.0],
            ['period' => '2026-Q2', 'total' => 750.0],
        ];

        $rows = $builder->buildRows(ReportIdentifier::SALES_SUMMARY, $items);

        $this->assertCount(2, $rows);
        $this->assertSame('2026-Q1', $rows[0]['period']);
        $this->assertSame('2026-Q2', $rows[1]['period']);
        $this->assertEqualsWithDelta(105.0, $rows[0]['tax'], 0.001);  // 500 * 0.21
        $this->assertEqualsWithDelta(157.5, $rows[1]['tax'], 0.001);  // 750 * 0.21
    }

    // ── Column Order Dependency Test ───────────────────────────

    public function testColumnOrderMattersForDependentResolvers(): void
    {
        // This test demonstrates WHY resolved values must feed back into context.
        // 'tax' depends on 'total', and 'net_total' depends on both.
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $factory = new ReportTemplateFactory($entityManager);
        $builder = new ReportRowBuilder($factory);

        $row = $builder->buildRow(
            ReportIdentifier::SALES_SUMMARY,
            ['period' => '2026-Q1', 'total' => 200.0]
        );

        // If context enrichment works correctly:
        // tax = 200 * 0.21 = 42.0
        // net_total = 200 - 42 = 158.0
        $this->assertEqualsWithDelta(42.0, $row['tax'], 0.001);
        $this->assertEqualsWithDelta(158.0, $row['net_total'], 0.001);
    }
}
