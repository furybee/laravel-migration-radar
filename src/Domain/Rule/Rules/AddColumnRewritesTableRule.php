<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Domain\Database\Platform;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * Adding a column is instant on recent engines, but some definitions or versions still rebuild the table.
 * Even on MySQL 8.0.29+, ADD COLUMN falls back to a rebuild with a FULLTEXT index, ROW_FORMAT=COMPRESSED
 * or after 64 instant changes: the schema does not tell, so those cases are documented, not reported.
 */
final readonly class AddColumnRewritesTableRule implements Rule
{
    public function id(): string
    {
        return 'add-column-rewrites-table';
    }

    public function description(): string
    {
        return 'Adding a column in a way that rebuilds a large table';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operationsOnExistingTables($context) as $operation) {
            if (!$operation instanceof AddColumn || !$context->isLargeTable($operation->tableName)) {
                continue;
            }

            $risk = $this->risk($operation->column, $context->platform);
            if ($risk === null) {
                continue;
            }

            [$severity, $reason] = $risk;
            $issues[] = new Issue(
                $this->id(),
                $severity,
                "Adding column \"{$operation->column->name}\" to {$context->describeTableSize($operation->tableName)} {$reason}.",
                'Run it with an online schema change tool (pt-online-schema-change, or gh-ost when the table has no foreign keys), '
                    . 'or append a plain column and fill it in batches.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }

    /**
     * @return array{Severity, string}|null
     */
    private function risk(Column $column, Platform $platform): ?array
    {
        if ($platform->is(Driver::Sqlite)) {
            return null;
        }

        if ($column->isStoredGenerated) {
            return [Severity::Error->onPlatformKnown($platform->isKnown()), 'as a stored generated column computes it for every row while blocking writes'];
        }

        if ($column->isAutoIncrement) {
            return [Severity::Error->onPlatformKnown($platform->isKnown()), 'as an auto-increment column numbers every row while blocking writes'];
        }

        return match ($platform->driver) {
            Driver::MySql => $this->mySqlRisk($column, $platform),
            Driver::MariaDb => $this->mariaDbRisk($column, $platform),
            Driver::PostgreSql => $this->postgreSqlRisk($column, $platform),
            Driver::Unknown => $this->unknownPlatformRisk($column),
            Driver::Sqlite => null,
        };
    }

    /**
     * @return array{Severity, string}|null
     */
    private function mySqlRisk(Column $column, Platform $platform): ?array
    {
        $isInstant = $platform->versionAtLeast('8.0.29') || ($platform->versionAtLeast('8.0.12') && !$column->isPositioned);

        return $isInstant ? null : [Severity::Warning, "rebuilds the table on {$platform->describe()} (instant only from MySQL 8.0.12, 8.0.29 when placed with after() or first())"];
    }

    /**
     * @return array{Severity, string}|null
     */
    private function mariaDbRisk(Column $column, Platform $platform): ?array
    {
        $isInstant = $platform->versionAtLeast('10.4') || ($platform->versionAtLeast('10.3.2') && !$column->isPositioned);

        return $isInstant ? null : [Severity::Warning, "rebuilds the table on {$platform->describe()} (instant only from MariaDB 10.3.2, 10.4 when placed with after() or first())"];
    }

    /**
     * Without the platform, only definitions that rebuild the table on some supported engine are worth a warning.
     *
     * @return array{Severity, string}|null
     */
    private function unknownPlatformRisk(Column $column): ?array
    {
        return match (true) {
            $column->isPositioned => [Severity::Warning, 'with after() or first() rebuilds the table before MySQL 8.0.29 and MariaDB 10.4'],
            $column->hasExpressionDefault => [Severity::Warning, 'with an expression default rewrites the table on PostgreSQL if the expression is volatile'],
            default => null,
        };
    }

    /**
     * @return array{Severity, string}|null
     */
    private function postgreSqlRisk(Column $column, Platform $platform): ?array
    {
        if (!$column->hasDefault) {
            return null;
        }

        if (!$platform->versionAtLeast('11')) {
            return [Severity::Error, "with a default rewrites the whole table under an exclusive lock on {$platform->describe()} (metadata only from PostgreSQL 11)"];
        }

        return $column->hasExpressionDefault
            ? [Severity::Warning, 'with an expression default rewrites the whole table under an exclusive lock if the expression is volatile (random(), gen_random_uuid()...)']
            : null;
    }
}
