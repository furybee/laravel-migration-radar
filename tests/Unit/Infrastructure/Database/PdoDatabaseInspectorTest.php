<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Infrastructure\Database;

use Furybee\MigrationRadar\Application\Port\DatabaseUnreachable;
use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Infrastructure\Database\DatabaseUrl;
use Furybee\MigrationRadar\Infrastructure\Database\PdoDatabaseInspector;
use PHPUnit\Framework\TestCase;

/**
 * MySQL and PostgreSQL queries are covered by the integration suite, against real servers.
 */
final class PdoDatabaseInspectorTest extends TestCase
{
    public function testReadsTheSqliteVersion(): void
    {
        $inspector = new PdoDatabaseInspector(static fn() => new \PDO('sqlite::memory:'));

        $platform = $inspector->platform();

        $this->assertSame(Driver::Sqlite, $platform->driver);
        $this->assertMatchesRegularExpression('/^3\.\d+/', (string) $platform->version);
        $this->assertSame([], $inspector->tableRows());
    }

    public function testConnectsOnlyWhenAsked(): void
    {
        $connections = new \ArrayObject();
        $inspector = new PdoDatabaseInspector(static function () use ($connections) {
            $connections->append(true);

            return new \PDO('sqlite::memory:');
        });
        $this->assertCount(0, $connections);

        $inspector->platform();
        $inspector->tableRows();

        $this->assertCount(1, $connections);
    }

    public function testReportsAnUnreachableDatabase(): void
    {
        $inspector = PdoDatabaseInspector::fromUrl(DatabaseUrl::parse('sqlite:///nonexistent/directory/app.sqlite'));

        $this->expectException(DatabaseUnreachable::class);

        $inspector->platform();
    }
}
