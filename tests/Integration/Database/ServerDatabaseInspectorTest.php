<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Integration\Database;

use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Infrastructure\Database\DatabaseUrl;
use Furybee\MigrationRadar\Infrastructure\Database\PdoDatabaseInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the servers given by MIGRATION_RADAR_TEST_MYSQL_URL / MIGRATION_RADAR_TEST_PGSQL_URL, skipped otherwise.
 */
final class ServerDatabaseInspectorTest extends TestCase
{
    private const ROWS = 250;

    /**
     * @return iterable<string, array{string, list<Driver>, string}>
     */
    public static function servers(): iterable
    {
        yield 'MySQL or MariaDB' => ['MIGRATION_RADAR_TEST_MYSQL_URL', [Driver::MySql, Driver::MariaDb], 'ANALYZE TABLE radar_probe'];
        yield 'PostgreSQL' => ['MIGRATION_RADAR_TEST_PGSQL_URL', [Driver::PostgreSql], 'ANALYZE radar_probe'];
    }

    /**
     * @param list<Driver> $expectedDrivers
     */
    #[DataProvider('servers')]
    public function testReadsTheServerVersionAndTheTableEstimates(string $variable, array $expectedDrivers, string $analyze): void
    {
        $url = getenv($variable);
        if (!is_string($url) || $url === '') {
            $this->markTestSkipped("{$variable} is not set");
        }

        $parsed = DatabaseUrl::parse($url);
        $connection = new \PDO($parsed->pdoDsn, $parsed->user, $parsed->password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $connection->exec('DROP TABLE IF EXISTS radar_probe');
        $connection->exec('CREATE TABLE radar_probe (id INT PRIMARY KEY)');
        $connection->exec('INSERT INTO radar_probe (id) VALUES ' . implode(',', array_map(static fn(int $i) => "({$i})", range(1, self::ROWS))));
        $statement = $connection->query($analyze);
        if ($statement !== false) {
            $statement->fetchAll();
        }

        try {
            $inspector = PdoDatabaseInspector::fromUrl($parsed);
            $platform = $inspector->platform();
            $rows = $inspector->tableRows();
        } finally {
            $connection->exec('DROP TABLE radar_probe');
        }

        $this->assertContains($platform->driver, $expectedDrivers);
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', (string) $platform->version);
        $this->assertArrayHasKey('radar_probe', $rows);
        $this->assertEqualsWithDelta(self::ROWS, $rows['radar_probe'], self::ROWS * 0.2, 'Row estimates are approximate');
    }
}
