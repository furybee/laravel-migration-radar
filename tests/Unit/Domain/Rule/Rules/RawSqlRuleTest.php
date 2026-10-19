<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
use Furybee\MigrationRadar\Domain\Rule\Rules\RawSqlRule;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\IssueAssertions;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\TestCase;

final class RawSqlRuleTest extends TestCase
{
    public function testShowsAnExcerptOfTheQueryOnOneLine(): void
    {
        $sql = "ALTER TABLE users\n    ADD COLUMN " . str_repeat('x', 100);
        $migration = MigrationBuilder::migration()->with(new RawStatement($sql, 11))->build();

        $issue = IssueAssertions::assertSingleIssue((new RawSqlRule())->check($migration, Contexts::on('mysql:8.4')), Severity::Info, 11);

        $this->assertStringStartsWith('Raw SQL is not analysed: ALTER TABLE users ADD COLUMN xx', $issue->message);
        $this->assertStringEndsWith('...', $issue->message);
    }

    public function testDescribesDynamicQueries(): void
    {
        $migration = MigrationBuilder::migration()->with(new RawStatement(null, 11))->build();

        $issues = (new RawSqlRule())->check($migration, Contexts::on('mysql:8.4'));

        $this->assertStringContainsString('(dynamic query)', $issues[0]->message);
    }
}
