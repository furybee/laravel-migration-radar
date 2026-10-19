<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Database;

use Furybee\MigrationRadar\Application\Port\DatabaseInspector;
use Furybee\MigrationRadar\Application\Port\DatabaseUnreachable;
use Furybee\MigrationRadar\Domain\Database\Driver;
use Furybee\MigrationRadar\Domain\Database\Platform;

/**
 * Reads the server version and the row estimates the engine keeps: no table is scanned.
 */
final class PdoDatabaseInspector implements DatabaseInspector
{
    private ?\PDO $connection = null;

    /**
     * @param \Closure(): \PDO $connect called on first use only: no connection when nothing is inspected
     */
    public function __construct(private readonly \Closure $connect)
    {
    }

    public static function fromUrl(DatabaseUrl $url): self
    {
        return new self(static fn() => new \PDO($url->pdoDsn, $url->user, $url->password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 5,
        ]));
    }

    public function platform(): Platform
    {
        $driver = $this->driver();
        $version = $this->value(match ($driver) {
            Driver::PostgreSql => 'SHOW server_version',
            Driver::Sqlite => 'SELECT sqlite_version()',
            default => 'SELECT VERSION()',
        });

        return Platform::fromServerVersion($driver, $version);
    }

    public function tableRows(): array
    {
        $query = match ($this->driver()) {
            Driver::MySql, Driver::MariaDb => "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'",
            // reltuples is -1 for a table never analysed: its size is unknown, not zero
            Driver::PostgreSql => "SELECT c.relname, c.reltuples::bigint FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relkind IN ('r', 'p') AND n.nspname = ANY (current_schemas(false)) AND c.reltuples >= 0",
            default => null,
        };
        if ($query === null) {
            return [];
        }

        $rows = [];
        foreach ($this->query($query)->fetchAll(\PDO::FETCH_NUM) as $row) {
            if (is_array($row) && is_string($row[0]) && is_numeric($row[1])) {
                $rows[$row[0]] = (int) $row[1];
            }
        }

        return $rows;
    }

    private function driver(): Driver
    {
        $name = $this->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return Driver::tryFrom(is_string($name) ? $name : '') ?? Driver::Unknown;
    }

    private function value(string $sql): string
    {
        $value = $this->query($sql)->fetchColumn();

        return is_scalar($value) ? (string) $value : '';
    }

    private function query(string $sql): \PDOStatement
    {
        try {
            $statement = $this->connection()->query($sql);
        } catch (\PDOException $e) {
            throw new DatabaseUnreachable("Cannot inspect the database: {$e->getMessage()}", 0, $e);
        }

        return $statement ?: throw new DatabaseUnreachable("Cannot inspect the database: {$sql} failed");
    }

    private function connection(): \PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        try {
            $connection = ($this->connect)();
            $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new DatabaseUnreachable("Cannot connect to the database: {$e->getMessage()}", 0, $e);
        }

        return $this->connection = $connection;
    }
}
