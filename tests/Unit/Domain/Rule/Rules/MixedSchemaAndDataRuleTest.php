<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Migration\Operation\DataChange;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
use Furybee\MigrationRadar\Domain\Rule\Rules\MixedSchemaAndDataRule;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\IssueAssertions;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\TestCase;

final class MixedSchemaAndDataRuleTest extends TestCase
{
    public function testReportsTheFirstDataChangeOfAMigrationThatAlsoChangesTheSchema(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(
                new AddColumn('users', new Column('status', 'string', isNullable: true), 3),
                new DataChange('users', "DB::table('users')->update()", 9),
                new DataChange('users', "DB::table('users')->delete()", 14),
            )
            ->build();

        $issues = (new MixedSchemaAndDataRule())->check($migration, Contexts::on('mysql:8.4'));

        $this->assertStringContainsString("DB::table('users')->update()", IssueAssertions::assertSingleIssue($issues, Severity::Warning, 9)->message);
    }

    public function testAcceptsDataOnlyMigrations(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(new RawStatement('UPDATE users SET status = 1', 3), new DataChange('users', 'User::...->update()', 9))
            ->build();

        $this->assertSame([], (new MixedSchemaAndDataRule())->check($migration, Contexts::on('mysql:8.4')));
    }
}
