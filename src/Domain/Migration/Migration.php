<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DataChange;
use Furybee\MigrationRadar\Domain\Migration\Operation\Operation;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameTable;

/**
 * What a migration does when it runs (its `up` direction).
 */
final readonly class Migration
{
    /** Table name only known at runtime: it may be any table */
    public const DYNAMIC_TABLE = '(dynamic)';

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
     * Operations on tables that already hold data and serve the deployed code:
     * not the tables created by this migration, nor by unreleased migrations before it.
     *
     * @return list<Operation>
     */
    public function operationsOnExistingTables(DatabaseContext $context): array
    {
        $createdTables = [];
        $operations = [];
        foreach ($this->operations as $operation) {
            if ($operation instanceof CreateTable) {
                if ($operation->tableName !== self::DYNAMIC_TABLE) {
                    $createdTables[$operation->tableName] = true;
                }
                continue;
            }

            $table = $operation->table();
            if ($table === null || (!isset($createdTables[$table]) && !$context->isNewTable($table))) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    /**
     * Tables that exist only once this migration ran, renames of new tables included.
     *
     * @param list<string> $alreadyNewTables
     * @return list<string>
     */
    public function newTablesAfter(array $alreadyNewTables): array
    {
        $tables = array_fill_keys($alreadyNewTables, true);
        foreach ($this->operations as $operation) {
            if ($operation instanceof CreateTable && $operation->tableName !== self::DYNAMIC_TABLE) {
                $tables[$operation->tableName] = true;
            } elseif ($operation instanceof RenameTable && isset($tables[$operation->from])) {
                unset($tables[$operation->from]);
                $tables[$operation->to] = true;
            }
        }

        return array_map('strval', array_keys($tables));
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
     * Data written to tables that already existed: filling a table just created is part of creating it.
     *
     * @return list<DataChange>
     */
    public function dataChangesOnExistingTables(DatabaseContext $context): array
    {
        return array_values(array_filter(
            $this->operationsOnExistingTables($context),
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
