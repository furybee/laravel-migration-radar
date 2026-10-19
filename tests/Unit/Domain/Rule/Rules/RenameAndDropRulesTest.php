<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\Operation;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameTable;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Rules\DropColumnRule;
use Furybee\MigrationRadar\Domain\Rule\Rules\DropTableRule;
use Furybee\MigrationRadar\Domain\Rule\Rules\RenameColumnRule;
use Furybee\MigrationRadar\Domain\Rule\Rules\RenameTableRule;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Contexts;
use Furybee\MigrationRadar\Tests\Support\IssueAssertions;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * These rules protect the code deployed during the release: the table size does not matter.
 */
final class RenameAndDropRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{Rule, Operation, Severity}>
     */
    public static function rules(): iterable
    {
        yield 'rename column' => [new RenameColumnRule(), new RenameColumn(Contexts::SMALL_TABLE, 'name', 'label', 8), Severity::Error];
        yield 'rename table' => [new RenameTableRule(), new RenameTable(Contexts::SMALL_TABLE, 'preferences', 8), Severity::Error];
        yield 'drop column' => [new DropColumnRule(), new DropColumn(Contexts::SMALL_TABLE, 'legacy', 8), Severity::Warning];
        yield 'drop table' => [new DropTableRule(), new DropTable(Contexts::SMALL_TABLE, 8), Severity::Warning];
    }

    #[DataProvider('rules')]
    public function testReportsOperationsOnExistingTablesWhateverTheirSize(Rule $rule, Operation $operation, Severity $severity): void
    {
        $migration = MigrationBuilder::migration()->with($operation)->build();

        IssueAssertions::assertSingleIssue($rule->check($migration, Contexts::on('mysql:8.4')), $severity, 8);
    }

    #[DataProvider('rules')]
    public function testIgnoresTablesCreatedByTheSameMigration(Rule $rule, Operation $operation): void
    {
        $migration = MigrationBuilder::migration()->with(new CreateTable(Contexts::SMALL_TABLE, 2), $operation)->build();

        $this->assertSame([], $rule->check($migration, Contexts::on('mysql:8.4')));
    }
}
