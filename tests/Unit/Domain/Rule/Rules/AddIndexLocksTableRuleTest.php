<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\AddIndex;
use Furybee\MigrationRadar\Domain\Migration\Operation\IndexType;
use Furybee\MigrationRadar\Domain\Rule\Rules\AddIndexLocksTableRule;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddIndexLocksTableRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, IndexType, Severity|null}>
     */
    public static function cases(): iterable
    {
        yield 'PostgreSQL blocks writes for any index' => ['pgsql:16', IndexType::Index, Severity::Error];
        yield 'InnoDB builds a secondary index online' => ['mysql:8.0.35', IndexType::Index, null];
        yield 'InnoDB builds a unique index online' => ['mariadb:10.11', IndexType::Unique, null];
        yield 'InnoDB blocks writes for a full text index' => ['mysql:8.0.35', IndexType::FullText, Severity::Error];
        yield 'InnoDB blocks writes for a spatial index' => ['mysql:8.0.35', IndexType::Spatial, Severity::Error];
        yield 'InnoDB rebuilds the table for a primary key' => ['mysql:8.0.35', IndexType::Primary, Severity::Warning];
        yield 'unknown platform warns' => ['unknown', IndexType::Index, Severity::Warning];
        yield 'SQLite is ignored' => ['sqlite', IndexType::Index, null];
    }

    #[DataProvider('cases')]
    public function testReportsIndexBuildsThatBlockWrites(string $platform, IndexType $type, ?Severity $expected): void
    {
        $migration = MigrationBuilder::migration()->with(new AddIndex(Contexts::LARGE_TABLE, $type, ['email'], 5))->build();

        $issues = (new AddIndexLocksTableRule())->check($migration, Contexts::on($platform));

        $this->assertSame($expected, $issues[0]->severity ?? null);
    }

    public function testRecommendsConcurrentlyOnPostgreSql(): void
    {
        $migration = MigrationBuilder::migration()->with(new AddIndex(Contexts::LARGE_TABLE, IndexType::Index, ['email'], 5))->build();

        $issues = (new AddIndexLocksTableRule())->check($migration, Contexts::on('pgsql:16'));

        $this->assertStringContainsString('CONCURRENTLY', $issues[0]->recommendation);
        $this->assertStringContainsString('(email)', $issues[0]->message);
    }

    public function testIgnoresSmallTables(): void
    {
        $migration = MigrationBuilder::migration()->with(new AddIndex(Contexts::SMALL_TABLE, IndexType::Index, ['key'], 5))->build();

        $this->assertSame([], (new AddIndexLocksTableRule())->check($migration, Contexts::on('pgsql:16')));
    }
}
