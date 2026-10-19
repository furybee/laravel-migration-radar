<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support\Fakes;

use Furybee\MigrationRadar\Application\Port\MigrationNotParsable;
use Furybee\MigrationRadar\Application\Port\MigrationParser;
use Furybee\MigrationRadar\Domain\Migration\Migration;

final class InMemoryMigrationParser implements MigrationParser
{
    /** @var array<string, Migration> */
    private array $migrations = [];

    public function add(Migration $migration): self
    {
        $this->migrations[$migration->path] = $migration;

        return $this;
    }

    public function parse(string $path): Migration
    {
        return $this->migrations[$path] ?? throw MigrationNotParsable::at($path, 'syntax error');
    }
}
