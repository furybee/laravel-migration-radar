<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration;

use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DataChange;
use Furybee\MigrationRadar\Domain\Migration\Operation\Operation;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;

/**
 * What a migration does when it runs (its `up` direction).
 */
final readonly class Migration
{
    /**
     * @param list<Operation> $operations in execution order
     * @param list<Suppression> $suppressions
     */
    public function __construct(
        public string $path,
        public array $operations,
        public array $suppressions = [],
    ) {
    }

    /**
     * Operations on tables that already hold data: a table created by this very migration is empty.
     *
     * @return list<Operation>
     */
    public function operationsOnExistingTables(): array
    {
        $createdTables = [];
        $operations = [];
        foreach ($this->operations as $operation) {
            if ($operation instanceof CreateTable) {
                $createdTables[$operation->tableName] = true;
                continue;
            }

            if ($operation->table() === null || !isset($createdTables[$operation->table()])) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    public function changesSchema(): bool
    {
        foreach ($this->operations as $operation) {
            if (!$operation instanceof DataChange && !$operation instanceof RawStatement) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<DataChange>
     */
    public function dataChanges(): array
    {
        return array_values(array_filter(
            $this->operations,
            static fn(Operation $operation) => $operation instanceof DataChange,
        ));
    }

    public function isSuppressed(string $ruleId, int $line): bool
    {
        foreach ($this->suppressions as $suppression) {
            if ($suppression->covers($ruleId, $line)) {
                return true;
            }
        }

        return false;
    }
}
