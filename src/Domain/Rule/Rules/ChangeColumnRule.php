<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\ChangeColumn;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * `->change()` restates the whole column, so the actual change (type, length, nullability) is unknown.
 */
final readonly class ChangeColumnRule implements Rule
{
    public function id(): string
    {
        return 'change-column';
    }

    public function description(): string
    {
        return 'Changing a column of a large table';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $platform = $context->platform;
        if ($platform->is(Driver::Sqlite)) {
            return [];
        }

        $issues = [];
        foreach ($migration->operationsOnExistingTables($context) as $operation) {
            if (!$operation instanceof ChangeColumn || !$context->isLargeTable($operation->tableName)) {
                continue;
            }

            $isPostgreSql = $platform->is(Driver::PostgreSql);
            $issues[] = new Issue(
                $this->id(),
                Severity::Error->onPlatformKnown($platform->isKnown()),
                "Changing column \"{$operation->column->name}\" of {$context->describeTableSize($operation->tableName)} "
                    . ($isPostgreSql
                        ? 'rewrites the whole table under an exclusive lock when the type changes, and scans it when it becomes NOT NULL.'
                        : 'copies the whole table while blocking writes when the type or charset changes, and rebuilds it when it becomes NOT NULL.'),
                $isPostgreSql
                    ? 'Add a new column, backfill it in batches and switch the code to it. To make a column NOT NULL on PostgreSQL 12+, '
                        . 'add a CHECK (column IS NOT NULL) NOT VALID constraint, validate it, then set NOT NULL: no scan under lock.'
                    : 'Run it with an online schema change tool (pt-online-schema-change, or gh-ost when the table has no foreign keys), '
                        . 'or add a new column, backfill it in batches and switch the code to it.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }
}
