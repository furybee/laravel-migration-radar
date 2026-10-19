<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Rule\Rules\NotNullColumnWithoutDefaultRule;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\IssueAssertions;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotNullColumnWithoutDefaultRuleTest extends TestCase
{
    public function testReportsANotNullColumnWithoutDefaultEvenOnASmallTable(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(new AddColumn(Contexts::SMALL_TABLE, new Column('code', 'string'), 12))
            ->build();

        $issues = (new NotNullColumnWithoutDefaultRule())->check($migration, Contexts::on('mysql:8.0.35'));

        $issue = IssueAssertions::assertSingleIssue($issues, Severity::Error, 12);
        $this->assertStringContainsString('"code"', $issue->message);
    }

    /**
     * @return iterable<string, array{Column}>
     */
    public static function columnsFilledAutomatically(): iterable
    {
        yield 'nullable' => [new Column('code', 'string', isNullable: true)];
        yield 'with a default' => [new Column('code', 'string', hasDefault: true)];
        yield 'auto increment' => [new Column('id', 'bigIncrements', isAutoIncrement: true)];
        yield 'stored generated' => [new Column('total', 'integer', isStoredGenerated: true)];
    }

    #[DataProvider('columnsFilledAutomatically')]
    public function testAcceptsColumnsFilledAutomatically(Column $column): void
    {
        $migration = MigrationBuilder::migration()->with(new AddColumn(Contexts::LARGE_TABLE, $column, 3))->build();

        $this->assertSame([], (new NotNullColumnWithoutDefaultRule())->check($migration, Contexts::on('mysql:8.0.35')));
    }

    public function testAcceptsColumnsOfATableCreatedByTheSameMigration(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(new CreateTable('orders', 1), new AddColumn('orders', new Column('code', 'string'), 9))
            ->build();

        $this->assertSame([], (new NotNullColumnWithoutDefaultRule())->check($migration, Contexts::on('mysql:8.0.35')));
    }
}
