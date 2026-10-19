<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameColumn;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

final readonly class RenameColumnRule implements Rule
{
    public function id(): string
    {
        return 'rename-column';
    }

    public function description(): string
    {
        return 'Renaming a column the deployed code still uses';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operationsOnExistingTables($context) as $operation) {
            if (!$operation instanceof RenameColumn) {
                continue;
            }

            $issues[] = new Issue(
                $this->id(),
                Severity::Error,
                "Renaming column \"{$operation->tableName}.{$operation->from}\" to \"{$operation->to}\" "
                    . 'breaks the code currently deployed until the new release is live.',
                'Add the new column, write to both, backfill, switch reads to the new column, '
                    . 'then drop the old one in a later release.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }
}
