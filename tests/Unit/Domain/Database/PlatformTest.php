<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Database;

use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Domain\Database\InvalidPlatform;
use Furybee\MigrationRadar\Domain\Database\Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Driver, string|null}>
     */
    public static function notations(): iterable
    {
        yield 'driver and version' => ['mysql:8.0.35', Driver::MySql, '8.0.35'];
        yield 'driver only' => ['mariadb', Driver::MariaDb, null];
        yield 'postgres alias' => ['postgres:16', Driver::PostgreSql, '16'];
        yield 'case and spaces' => [' PgSQL:15.4 ', Driver::PostgreSql, '15.4'];
    }

    #[DataProvider('notations')]
    public function testParsesTheConfigurationNotation(string $notation, Driver $driver, ?string $version): void
    {
        $platform = Platform::fromString($notation);

        $this->assertSame($driver, $platform->driver);
        $this->assertSame($version, $platform->version);
    }

    public function testRejectsUnknownDatabases(): void
    {
        $this->expectException(InvalidPlatform::class);

        Platform::fromString('oracle:19');
    }

    /**
     * @return iterable<string, array{Driver, string, Driver, string}>
     */
    public static function serverVersions(): iterable
    {
        yield 'MySQL' => [Driver::MySql, '8.0.39', Driver::MySql, '8.0.39'];
        yield 'MariaDB behind the MySQL driver' => [Driver::MySql, '10.11.6-MariaDB-1:10.11.6+maria~ubu2204', Driver::MariaDb, '10.11.6'];
        yield 'PostgreSQL with build details' => [Driver::PostgreSql, '16.2 (Debian 16.2-1.pgdg120+2)', Driver::PostgreSql, '16.2'];
    }

    #[DataProvider('serverVersions')]
    public function testReadsWhatTheServerReports(Driver $connectedWith, string $reported, Driver $driver, string $version): void
    {
        $platform = Platform::fromServerVersion($connectedWith, $reported);

        $this->assertSame($driver, $platform->driver);
        $this->assertSame($version, $platform->version);
    }

    public function testTreatsAnUnknownVersionAsTheOldest(): void
    {
        $this->assertFalse(Platform::fromString('mysql')->versionAtLeast('5.0'));
        $this->assertTrue(Platform::fromString('mysql:8.0.35')->versionAtLeast('8.0.12'));
        $this->assertFalse(Platform::fromString('mysql:8.0')->versionAtLeast('8.0.12'));
    }

    public function testDescribesItself(): void
    {
        $this->assertSame('PostgreSQL 16', Platform::fromString('pgsql:16')->describe());
        $this->assertSame('unknown database', Platform::unknown()->describe());
    }
}
