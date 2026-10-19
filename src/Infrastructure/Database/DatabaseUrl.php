<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Database;

use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;

/**
 * `mysql://user:password@host:3306/database`, `pgsql://...`, `sqlite:///absolute/path.sqlite`.
 */
final readonly class DatabaseUrl
{
    private const SCHEMES = ['mysql' => 'mysql', 'mariadb' => 'mysql', 'pgsql' => 'pgsql', 'postgres' => 'pgsql', 'postgresql' => 'pgsql', 'sqlite' => 'sqlite'];
    private const DEFAULT_PORTS = ['mysql' => 3306, 'pgsql' => 5432];

    private function __construct(
        public string $pdoDsn,
        public ?string $user,
        public ?string $password,
    ) {
    }

    public static function parse(string $url): self
    {
        $scheme = strtolower((string) strstr($url, '://', true));
        $driver = self::SCHEMES[$scheme] ?? throw new InvalidConfiguration('Unsupported database URL, expected mysql://, pgsql:// or sqlite://');

        if ($driver === 'sqlite') {
            return new self('sqlite:' . substr($url, strlen('sqlite://')), null, null);
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            throw new InvalidConfiguration('Invalid database URL, expected scheme://user:password@host:port/database');
        }

        return new self(
            sprintf('%s:host=%s;port=%d;dbname=%s', $driver, $parts['host'], $parts['port'] ?? self::DEFAULT_PORTS[$driver], ltrim($parts['path'], '/')),
            isset($parts['user']) ? rawurldecode($parts['user']) : null,
            isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
        );
    }
}
