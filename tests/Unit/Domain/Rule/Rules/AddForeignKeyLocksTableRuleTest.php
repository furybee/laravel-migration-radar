<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\AddForeignKey;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Rule\Rules\AddForeignKeyLocksTableRule;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\IssueAssertions;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\TestCase;

final class AddForeignKeyLocksTableRuleTest extends TestCase
{
    public function testReportsTheTableCopyOnMySql(): void
    {
        $issues = (new AddForeignKeyLocksTableRule())->check($this->addingForeignKeyTo(Contexts::LARGE_TABLE), Contexts::on('mysql:8.0.35'));

        $issue = IssueAssertions::assertSingleIssue($issues, Severity::Error, 6);
        $this->assertStringContainsString('ALGORITHM=COPY', $issue->message);
        $this->assertStringContainsString('referencing "companies"', $issue->message);
    }

    public function testRecommendsNotValidOnPostgreSql(): void
    {
        $issues = (new AddForeignKeyLocksTableRule())->check($this->addingForeignKeyTo(Contexts::LARGE_TABLE), Contexts::on('pgsql:16'));

        $this->assertStringContainsString('NOT VALID', IssueAssertions::assertSingleIssue($issues, Severity::Error, 6)->recommendation);
    }

    public function testWarnsOnAnUnknownPlatform(): void
    {
        $issues = (new AddForeignKeyLocksTableRule())->check($this->addingForeignKeyTo(Contexts::LARGE_TABLE), Contexts::on('unknown'));

        IssueAssertions::assertSingleIssue($issues, Severity::Warning, 6);
    }

    public function testIgnoresSmallAndNewTables(): void
    {
        $newTable = MigrationBuilder::migration()
            ->with(new CreateTable('orders', 1), new AddForeignKey('orders', ['company_id'], 'companies', 6))
            ->build();

        $this->assertSame([], (new AddForeignKeyLocksTableRule())->check($this->addingForeignKeyTo(Contexts::SMALL_TABLE), Contexts::on('mysql:8.0.35')));
        $this->assertSame([], (new AddForeignKeyLocksTableRule())->check($newTable, Contexts::on('mysql:8.0.35')));
    }

    private function addingForeignKeyTo(string $table): Migration
    {
        return MigrationBuilder::migration()->with(new AddForeignKey($table, ['company_id'], 'companies', 6))->build();
    }
}
