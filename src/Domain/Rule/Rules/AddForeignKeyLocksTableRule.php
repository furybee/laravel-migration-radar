<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddForeignKey;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * Adding a foreign key checks every existing row: InnoDB copies the table when foreign_key_checks is on,
 * PostgreSQL locks both tables during the validation.
 */
final readonly class AddForeignKeyLocksTableRule implements Rule
{
    public function id(): string
    {
        return 'add-foreign-key-locks-table';
    }

    public function description(): string
    {
        return 'Adding a foreign key to a large table';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $driver = $context->platform->driver;
        if ($driver === Driver::Sqlite) {
            return [];
        }

        $issues = [];
        foreach ($migration->operationsOnExistingTables() as $operation) {
            if (!$operation instanceof AddForeignKey || !$context->isLargeTable($operation->tableName)) {
                continue;
            }

            $references = $operation->referencedTable === null ? '' : " referencing \"{$operation->referencedTable}\"";
            $issues[] = new Issue(
                $this->id(),
                Severity::Error->onPlatformKnown($context->platform->isKnown()),
                sprintf('Adding foreign key on (%s)%s to %s %s.', implode(', ', $operation->columns), $references, $context->describeTableSize($operation->tableName), match ($driver) {
                    Driver::PostgreSql => 'locks both tables while every existing row is validated',
                    Driver::MySql, Driver::MariaDb => 'copies the whole table while blocking writes (foreign_key_checks forces ALGORITHM=COPY)',
                    default => 'validates every existing row while locking the table',
                }),
                $driver === Driver::PostgreSql
                    ? 'Add the constraint with NOT VALID (DB::statement), then run VALIDATE CONSTRAINT in a separate migration: it does not block writes.'
                    : 'Run it with an online schema change tool (gh-ost, pt-online-schema-change), or declare the foreign key when creating the table.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }
}
