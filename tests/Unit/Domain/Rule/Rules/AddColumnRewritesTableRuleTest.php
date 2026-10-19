<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Rule\Rules\AddColumnRewritesTableRule;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddColumnRewritesTableRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Column, Severity|null}>
     */
    public static function cases(): iterable
    {
        $appended = new Column('code', 'string', isNullable: true);
        $positioned = new Column('code', 'string', isNullable: true, isPositioned: true);
        $withDefault = new Column('code', 'string', hasDefault: true);
        $withExpressionDefault = new Column('code', 'uuid', hasDefault: true, hasExpressionDefault: true);

        yield 'MySQL 5.7 rebuilds' => ['mysql:5.7.44', $appended, Severity::Warning];
        yield 'MySQL 8.0.12 appends instantly' => ['mysql:8.0.12', $appended, null];
        yield 'MySQL 8.0.28 rebuilds for after()' => ['mysql:8.0.28', $positioned, Severity::Warning];
        yield 'MySQL 8.0.29 places instantly' => ['mysql:8.0.29', $positioned, null];
        yield 'MySQL without patch version is assumed old' => ['mysql:8.0', $appended, Severity::Warning];
        yield 'MariaDB 10.3.2 appends instantly' => ['mariadb:10.3.2', $appended, null];
        yield 'MariaDB 10.3 rebuilds for after()' => ['mariadb:10.3.39', $positioned, Severity::Warning];
        yield 'MariaDB 10.4 places instantly' => ['mariadb:10.4', $positioned, null];
        yield 'PostgreSQL 10 rewrites for a default' => ['pgsql:10.23', $withDefault, Severity::Error];
        yield 'PostgreSQL 11 stores a constant default as metadata' => ['pgsql:11', $withDefault, null];
        yield 'PostgreSQL 16 may rewrite for an expression default' => ['pgsql:16', $withExpressionDefault, Severity::Warning];
        yield 'PostgreSQL without default never rewrites' => ['pgsql:9.6', $appended, null];
        yield 'stored generated column always computes every row' => ['mysql:8.4', new Column('total', 'integer', isStoredGenerated: true), Severity::Error];
        yield 'stored generated column on unknown platform is a warning' => ['unknown', new Column('total', 'integer', isStoredGenerated: true), Severity::Warning];
        yield 'unknown platform otherwise says nothing' => ['unknown', $positioned, null];
        yield 'SQLite is ignored' => ['sqlite', $positioned, null];
    }

    #[DataProvider('cases')]
    public function testReportsTableRebuildsDependingOnTheEngineAndVersion(string $platform, Column $column, ?Severity $expected): void
    {
        $migration = MigrationBuilder::migration()->with(new AddColumn(Contexts::LARGE_TABLE, $column, 7))->build();

        $issues = (new AddColumnRewritesTableRule())->check($migration, Contexts::on($platform));

        $this->assertSame($expected, $issues[0]->severity ?? null);
    }

    public function testIgnoresSmallTables(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(new AddColumn(Contexts::SMALL_TABLE, new Column('code', 'string', isNullable: true), 7))
            ->build();

        $this->assertSame([], (new AddColumnRewritesTableRule())->check($migration, Contexts::on('mysql:5.7')));
    }
}
