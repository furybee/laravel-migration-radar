<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Database;

/**
 * Database engine and version the migrations will run on.
 * An unknown version is treated as the oldest one: rules then assume the most expensive behaviour.
 */
final readonly class Platform
{
    private const ALIASES = ['postgres' => Driver::PostgreSql, 'postgresql' => Driver::PostgreSql, 'pgsql' => Driver::PostgreSql];

    public function __construct(
        public Driver $driver,
        public ?string $version = null,
    ) {
    }

    public static function unknown(): self
    {
        return new self(Driver::Unknown);
    }

    /**
     * Parses the configuration notation: `mysql`, `mysql:8.0.35`, `mariadb:10.11`, `pgsql:16`.
     */
    public static function fromString(string $platform): self
    {
        [$name, $version] = array_pad(explode(':', strtolower(trim($platform)), 2), 2, null);
        $driver = self::ALIASES[$name] ?? Driver::tryFrom((string) $name);
        if ($driver === null || $driver === Driver::Unknown) {
            throw new InvalidPlatform("Unknown database platform \"{$platform}\", expected mysql, mariadb, pgsql or sqlite");
        }

        if ($version !== null && $version !== '' && preg_match('/^\d+(\.\d+){0,2}$/', $version) !== 1) {
            throw new InvalidPlatform("Invalid version in \"{$platform}\", expected e.g. mysql:8.0.35");
        }

        return new self($driver, $version === null || $version === '' ? null : $version);
    }

    /**
     * Builds the platform from what the server reports: MariaDB identifies itself through MySQL drivers.
     */
    public static function fromServerVersion(Driver $driver, string $serverVersion): self
    {
        if ($driver === Driver::MySql && stripos($serverVersion, 'mariadb') !== false) {
            $driver = Driver::MariaDb;
        }

        return new self($driver, self::normalizeVersion($serverVersion));
    }

    public function isKnown(): bool
    {
        return $this->driver !== Driver::Unknown;
    }

    public function is(Driver $driver): bool
    {
        return $this->driver === $driver;
    }

    public function versionAtLeast(string $version): bool
    {
        return $this->version !== null && version_compare($this->version, $version, '>=');
    }

    public function describe(): string
    {
        $name = match ($this->driver) {
            Driver::MySql => 'MySQL',
            Driver::MariaDb => 'MariaDB',
            Driver::PostgreSql => 'PostgreSQL',
            Driver::Sqlite => 'SQLite',
            Driver::Unknown => 'unknown database',
        };

        return $this->version === null ? $name : "{$name} {$this->version}";
    }

    private static function normalizeVersion(string $version): string
    {
        return preg_match('/\d+(?:\.\d+){0,2}/', $version, $match) === 1 ? $match[0] : $version;
    }
}
