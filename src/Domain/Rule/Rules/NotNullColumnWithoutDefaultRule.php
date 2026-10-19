<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * Whatever the table size: the code still deployed during the release inserts rows without the new column.
 */
final readonly class NotNullColumnWithoutDefaultRule implements Rule
{
    public function id(): string
    {
        return 'not-null-column-without-default';
    }

    public function description(): string
    {
        return 'Adding a NOT NULL column without default to an existing table';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operationsOnExistingTables($context) as $operation) {
            if (!$operation instanceof AddColumn || $operation->column->isFilledAutomatically()) {
                continue;
            }

            $issues[] = new Issue(
                $this->id(),
                Severity::Error,
                "Adding NOT NULL column \"{$operation->column->name}\" without default to \"{$operation->tableName}\": "
                    . 'inserts from the code currently deployed will fail, and PostgreSQL rejects it on a non-empty table.',
                'Add the column as nullable (or with a default), deploy code that fills it, backfill existing rows, '
                    . 'then make it NOT NULL in a later migration.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }
}
