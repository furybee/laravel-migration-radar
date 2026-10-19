<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Application;

use Furybee\MigrationRadar\Application\Analysis\BuildDatabaseContext;
use Furybee\MigrationRadar\Application\Configuration\Configuration;
use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Domain\Database\Platform;
use Furybee\MigrationRadar\Tests\Support\Fakes\InMemoryDatabaseInspector;
use PHPUnit\Framework\TestCase;

final class BuildDatabaseContextTest extends TestCase
{
    public function testUsesWhatTheDatabaseReports(): void
    {
        $inspector = new InMemoryDatabaseInspector(Platform::fromString('mysql:8.0.39'), ['users' => 10]);

        $context = (new BuildDatabaseContext($inspector))->build(new Configuration(largeTableRows: 5, assumeLargeTables: false));

        $this->assertSame('MySQL 8.0.39', $context->platform->describe());
        $this->assertSame(['users' => 10], $context->tableRows);
        $this->assertSame(5, $context->largeTableRows);
        $this->assertFalse($context->assumeLargeWhenUnknown);
    }

    public function testLetsTheConfigurationWin(): void
    {
        $inspector = new InMemoryDatabaseInspector(Platform::fromString('mysql:8.0.39'), ['users' => 10, 'logs' => 3]);

        $context = (new BuildDatabaseContext($inspector))->build(new Configuration(platform: 'pgsql:16', tableRows: ['users' => 9_000_000]));

        $this->assertSame(Driver::PostgreSql, $context->platform->driver);
        $this->assertSame(['users' => 9_000_000, 'logs' => 3], $context->tableRows);
    }
}
