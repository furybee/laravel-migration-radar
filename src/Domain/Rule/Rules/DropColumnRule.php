<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropColumn;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

final readonly class DropColumnRule implements Rule
{
    public function id(): string
    {
        return 'drop-column';
    }

    public function description(): string
    {
        return 'Dropping a column the deployed code may still use';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operationsOnExistingTables() as $operation) {
            if (!$operation instanceof DropColumn) {
                continue;
            }

            $issues[] = new Issue(
                $this->id(),
                Severity::Warning,
                "Dropping column \"{$operation->tableName}.{$operation->columnName}\": the code currently deployed fails if it still reads or writes it.",
                'Remove every use of the column first (queries, model attributes, casts, fillable) and deploy it, '
                    . 'then drop the column in a later release.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }
}
