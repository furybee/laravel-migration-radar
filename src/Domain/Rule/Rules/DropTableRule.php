<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropTable;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

final readonly class DropTableRule implements Rule
{
    public function id(): string
    {
        return 'drop-table';
    }

    public function description(): string
    {
        return 'Dropping a table';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operationsOnExistingTables() as $operation) {
            if (!$operation instanceof DropTable) {
                continue;
            }

            $issues[] = new Issue(
                $this->id(),
                Severity::Warning,
                "Dropping table \"{$operation->tableName}\" cannot be undone and breaks the code currently deployed if it still uses it.",
                'Remove every use of the table and deploy it first, make sure a backup exists, then drop it in a later release.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }
}
