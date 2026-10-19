<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Configuration;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;

final readonly class Configuration
{
    /**
     * @param list<string> $paths where migrations live
     * @param string|null $platform `mysql:8.0.35`, `pgsql:16`... wins over what the database reports
     * @param array<string, int> $tableRows estimated rows per table, wins over the database statistics
     * @param list<string> $disabledRules
     * @param array<string, list<string>> $ignoredPaths path glob => rule ids (`*` for all)
     * @param string $tablePrefix Laravel's database prefix, stripped from the table names the database reports
     */
    public function __construct(
        public array $paths = ['database/migrations'],
        public ?string $platform = null,
        public ?string $dsn = null,
        public int $largeTableRows = DatabaseContext::DEFAULT_LARGE_TABLE_ROWS,
        public bool $assumeLargeTables = true,
        public array $tableRows = [],
        public array $disabledRules = [],
        public array $ignoredPaths = [],
        public string $tablePrefix = '',
    ) {
    }

    /**
     * @param list<string> $paths
     */
    public function withPaths(array $paths): self
    {
        return new self($paths, $this->platform, $this->dsn, $this->largeTableRows, $this->assumeLargeTables, $this->tableRows, $this->disabledRules, $this->ignoredPaths, $this->tablePrefix);
    }

    public function withPlatform(string $platform): self
    {
        return new self($this->paths, $platform, $this->dsn, $this->largeTableRows, $this->assumeLargeTables, $this->tableRows, $this->disabledRules, $this->ignoredPaths, $this->tablePrefix);
    }

    public function withDsn(string $dsn): self
    {
        return new self($this->paths, $this->platform, $dsn, $this->largeTableRows, $this->assumeLargeTables, $this->tableRows, $this->disabledRules, $this->ignoredPaths, $this->tablePrefix);
    }
}
