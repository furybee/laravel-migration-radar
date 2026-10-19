<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

use Furybee\MigrationRadar\Application\Configuration\Configuration;
use Furybee\MigrationRadar\Application\Port\DatabaseInspector;
use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Platform;

/**
 * Merges what the database reports with the configuration, which always wins: it is the deliberate input.
 *
 * A table missing from the statistics stays unknown, so presumed large: a missing table prefix or a table
 * PostgreSQL never analysed must not silence the lock rules. Tables created by the branch are known as new
 * through AnalyseMigrations::analyseUnreleased().
 */
final readonly class BuildDatabaseContext
{
    public function __construct(private DatabaseInspector $inspector)
    {
    }

    public function build(Configuration $configuration): DatabaseContext
    {
        $platform = $configuration->platform === null
            ? $this->inspector->platform()
            : Platform::fromString($configuration->platform);
        $databaseRows = self::withoutPrefix($this->inspector->tableRows(), $configuration->tablePrefix);

        return new DatabaseContext(
            $platform,
            $configuration->tableRows + $databaseRows,
            $configuration->largeTableRows,
            $configuration->assumeLargeTables,
        );
    }

    /**
     * The database knows `app_users`, migrations say `users` when Laravel adds a prefix.
     *
     * @param array<string, int> $rows
     * @return array<string, int>
     */
    private static function withoutPrefix(array $rows, string $prefix): array
    {
        if ($prefix === '') {
            return $rows;
        }

        $unprefixed = [];
        foreach ($rows as $table => $count) {
            if (str_starts_with($table, $prefix)) {
                $unprefixed[substr($table, strlen($prefix))] = $count;
            }
        }

        return $unprefixed;
    }
}
