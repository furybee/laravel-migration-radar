<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameTable;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

final readonly class RenameTableRule implements Rule
{
    public function id(): string
    {
        return 'rename-table';
    }

    public function description(): string
    {
        return 'Renaming a table the deployed code still uses';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operationsOnExistingTables($context) as $operation) {
            if (!$operation instanceof RenameTable) {
                continue;
            }

            $issues[] = new Issue(
                $this->id(),
                Severity::Error,
                "Renaming table \"{$operation->from}\" to \"{$operation->to}\" breaks the code currently deployed until the new release is live.",
                'Create the new table, write to both, backfill, switch reads, then drop the old table in a later release '
                    . '(or point the model at the new name with a view during the transition).',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }
}
