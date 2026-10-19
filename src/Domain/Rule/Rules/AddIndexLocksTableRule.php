<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddIndex;
use Furybee\MigrationRadar\Domain\Migration\Operation\IndexType;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * PostgreSQL blocks writes while building any index unless CONCURRENTLY is used, which Laravel never does.
 * InnoDB builds secondary indexes online, but not full text and spatial ones, and a primary key rebuilds the table.
 */
final readonly class AddIndexLocksTableRule implements Rule
{
    private const POSTGRESQL_RECOMMENDATION = 'Create it with DB::statement(\'CREATE INDEX CONCURRENTLY ...\') '
        . 'in a migration declaring `public $withinTransaction = false;`.';

    public function id(): string
    {
        return 'add-index-locks-table';
    }

    public function description(): string
    {
        return 'Adding an index that blocks writes on a large table';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operationsOnExistingTables($context) as $operation) {
            if (!$operation instanceof AddIndex || !$context->isLargeTable($operation->tableName)) {
                continue;
            }

            $risk = $this->risk($operation->type, $context->platform->driver);
            if ($risk === null) {
                continue;
            }

            [$severity, $reason, $recommendation] = $risk;
            $issues[] = new Issue(
                $this->id(),
                $severity,
                sprintf('Adding %s index on (%s) to %s %s.', $operation->type->value, implode(', ', $operation->columns), $context->describeTableSize($operation->tableName), $reason),
                $recommendation,
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }

    /**
     * @return array{Severity, string, string}|null
     */
    private function risk(IndexType $type, Driver $driver): ?array
    {
        return match ($driver) {
            Driver::PostgreSql => [Severity::Error, 'blocks writes on PostgreSQL for the whole index build', self::POSTGRESQL_RECOMMENDATION],
            Driver::MySql, Driver::MariaDb => $this->innoDbRisk($type),
            Driver::Unknown => [Severity::Warning, 'may block writes depending on the database (always on PostgreSQL without CONCURRENTLY)', self::POSTGRESQL_RECOMMENDATION],
            Driver::Sqlite => null,
        };
    }

    /**
     * @return array{Severity, string, string}|null
     */
    private function innoDbRisk(IndexType $type): ?array
    {
        return match ($type) {
            IndexType::FullText, IndexType::Spatial => [Severity::Error, 'blocks writes during the build (InnoDB builds full text and spatial indexes offline)', 'Run it with an online schema change tool (gh-ost, pt-online-schema-change) or during a maintenance window.'],
            IndexType::Primary => [Severity::Warning, 'rebuilds the whole table', 'Prefer defining the primary key when creating the table, or run it with an online schema change tool.'],
            IndexType::Index, IndexType::Unique => null,
        };
    }
}
