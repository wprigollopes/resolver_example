<?php

declare(strict_types=1);

namespace App\Report\Enum;

/**
 * ReportIdentifier — the enum that maps a report ID string to a report type.
 *
 * In PHP 8.1+, backed enums (`: string`) can be created from raw strings
 * using ReportIdentifier::from('SALES_SUMMARY'). This replaces the old
 * pattern of using class constants + a match statement on raw strings.
 *
 * KEY CONCEPT: This enum only *identifies* the report. It does NOT define
 * the column list directly — that's the job of the ReportTemplateFactory.
 *
 * Why separate them?
 *   - Enums CANNOT receive injected services (no constructor injection).
 *   - Some resolvers need Doctrine's EntityManager (a service).
 *   - So: enum identifies → factory builds columns with proper DI.
 *
 * TODO (Alejandro): Add a new case for a report type of your choice.
 *
 * Think about: What happens if someone calls ReportIdentifier::from('UNKNOWN')?
 * (Hint: PHP throws a ValueError. Should you catch it? Where?)
 */
enum ReportIdentifier: string
{
    case SALES_SUMMARY   = 'SALES_SUMMARY';
    case INVENTORY_CHECK = 'INVENTORY_CHECK';
    case EMPLOYEE_PERFORMANCE = 'EMPLOYEE_PERFORMANCE';

    // TODO: Add a third case here. Choose a domain that makes sense
    //       (e.g., EMPLOYEE_REPORT, FINANCIAL_STATEMENT, ORDER_EXPORT).
    //       Then add its column definitions in ReportTemplateFactory.
}
