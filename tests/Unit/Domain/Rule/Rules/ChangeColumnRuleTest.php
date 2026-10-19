<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\ChangeColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Rule\Rules\ChangeColumnRule;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\IssueAssertions;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\TestCase;

final class ChangeColumnRuleTest extends TestCase
{
    public function testReportsChangesOfALargeTable(): void
    {
        $issues = (new ChangeColumnRule())->check($this->changing(Contexts::LARGE_TABLE), Contexts::on('mysql:8.0.35'));

        $issue = IssueAssertions::assertSingleIssue($issues, Severity::Error, 4);
        $this->assertStringContainsString('gh-ost', $issue->recommendation);
    }

    public function testRecommendsANotValidConstraintOnPostgreSql(): void
    {
        $issues = (new ChangeColumnRule())->check($this->changing(Contexts::LARGE_TABLE), Contexts::on('pgsql:16'));

        $this->assertStringContainsString('NOT VALID', IssueAssertions::assertSingleIssue($issues, Severity::Error, 4)->recommendation);
    }

    public function testDowngradesToAWarningOnAnUnknownPlatform(): void
    {
        $issues = (new ChangeColumnRule())->check($this->changing(Contexts::LARGE_TABLE), Contexts::on('unknown'));

        IssueAssertions::assertSingleIssue($issues, Severity::Warning, 4);
    }

    public function testIgnoresSmallTablesAndSqlite(): void
    {
        $this->assertSame([], (new ChangeColumnRule())->check($this->changing(Contexts::SMALL_TABLE), Contexts::on('mysql:8.0.35')));
        $this->assertSame([], (new ChangeColumnRule())->check($this->changing(Contexts::LARGE_TABLE), Contexts::on('sqlite')));
    }

    private function changing(string $table): Migration
    {
        return MigrationBuilder::migration()->with(new ChangeColumn($table, new Column('email', 'string'), 4))->build();
    }
}
