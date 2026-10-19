<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Configuration;

use Furybee\MigrationRadar\Application\Configuration\Configuration;
use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;

/**
 * Reads `migration-radar.php`, a file returning an array. Unknown keys are rejected: a typo must not go unnoticed.
 */
final readonly class PhpConfigurationLoader
{
    public const DEFAULT_FILE = 'migration-radar.php';

    private const KEYS = ['paths', 'platform', 'dsn', 'large_table_rows', 'assume_large_tables', 'table_rows', 'table_prefix', 'disabled_rules', 'ignore'];

    public function load(string $file): Configuration
    {
        if (!is_file($file)) {
            throw new InvalidConfiguration("Configuration file not found: {$file}");
        }

        $values = (static fn() => require $file)();
        if (!is_array($values)) {
            throw new InvalidConfiguration("{$file} must return an array");
        }

        $unknown = array_diff(array_keys($values), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidConfiguration('Unknown configuration key(s): ' . implode(', ', $unknown) . '. Allowed: ' . implode(', ', self::KEYS));
        }

        $defaults = new Configuration();

        return new Configuration(
            paths: isset($values['paths']) ? self::strings($values['paths'], 'paths') : $defaults->paths,
            platform: self::nullableString($values['platform'] ?? null, 'platform'),
            dsn: self::nullableString($values['dsn'] ?? null, 'dsn'),
            largeTableRows: self::positiveInt($values['large_table_rows'] ?? $defaults->largeTableRows, 'large_table_rows'),
            assumeLargeTables: self::bool($values['assume_large_tables'] ?? $defaults->assumeLargeTables, 'assume_large_tables'),
            tableRows: self::tableRows($values['table_rows'] ?? []),
            disabledRules: self::strings($values['disabled_rules'] ?? [], 'disabled_rules'),
            ignoredPaths: self::ignoredPaths($values['ignore'] ?? []),
            tablePrefix: self::nullableString($values['table_prefix'] ?? null, 'table_prefix') ?? '',
        );
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value, string $key): array
    {
        $values = is_array($value) ? array_values($value) : [$value];
        foreach ($values as $item) {
            if (!is_string($item)) {
                throw new InvalidConfiguration("\"{$key}\" must be a list of strings");
            }
        }

        /** @var list<string> $values */
        return $values;
    }

    private static function nullableString(mixed $value, string $key): ?string
    {
        if ($value !== null && !is_string($value)) {
            throw new InvalidConfiguration("\"{$key}\" must be a string");
        }

        return $value === '' ? null : $value;
    }

    private static function positiveInt(mixed $value, string $key): int
    {
        if (!is_int($value) || $value < 1) {
            throw new InvalidConfiguration("\"{$key}\" must be a positive integer");
        }

        return $value;
    }

    private static function bool(mixed $value, string $key): bool
    {
        if (!is_bool($value)) {
            throw new InvalidConfiguration("\"{$key}\" must be a boolean");
        }

        return $value;
    }

    /**
     * @return array<string, int>
     */
    private static function tableRows(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidConfiguration('"table_rows" must map table names to row counts');
        }

        $rows = [];
        foreach ($value as $table => $count) {
            if (!is_string($table) || !is_int($count) || $count < 0) {
                throw new InvalidConfiguration('"table_rows" must map table names to row counts');
            }
            $rows[$table] = $count;
        }

        return $rows;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function ignoredPaths(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidConfiguration('"ignore" must map path globs to rule ids');
        }

        $ignored = [];
        foreach ($value as $glob => $ruleIds) {
            if (!is_string($glob)) {
                throw new InvalidConfiguration('"ignore" must map path globs to rule ids');
            }
            $ignored[$glob] = self::strings($ruleIds, "ignore.{$glob}");
        }

        return $ignored;
    }
}
