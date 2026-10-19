<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Database;

/**
 * What is known about the target database. The cost of most schema changes depends on it.
 */
final readonly class DatabaseContext
{
    public const DEFAULT_LARGE_TABLE_ROWS = 1_000_000;

    /**
     * @param array<string, int> $tableRows estimated rows per table
     * @param bool $assumeLargeWhenUnknown without statistics, a table is presumed large: false negatives cost an outage
     */
    public function __construct(
        public Platform $platform,
        public array $tableRows = [],
        public int $largeTableRows = self::DEFAULT_LARGE_TABLE_ROWS,
        public bool $assumeLargeWhenUnknown = true,
    ) {
    }

    public function isLargeTable(string $table): bool
    {
        $rows = $this->tableRows[$table] ?? null;

        return $rows === null ? $this->assumeLargeWhenUnknown : $rows >= $this->largeTableRows;
    }

    public function describeTableSize(string $table): string
    {
        $rows = $this->tableRows[$table] ?? null;

        return $rows === null ? "\"{$table}\"" : sprintf('"%s" (~%s rows)', $table, number_format($rows));
    }
}
