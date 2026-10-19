<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Migration;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Platform;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DataChange;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameTable;
use Furybee\MigrationRadar\Domain\Migration\Suppression;
use Furybee\MigrationRadar\Tests\Support\MigrationBuilder;
use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase
{
    public function testLeavesOutOperationsOnTablesItCreates(): void
    {
        $onUsers = new AddColumn('users', new Column('a', 'string'), 2);
        $raw = new RawStatement('SELECT 1', 3);
        $migration = MigrationBuilder::migration()
            ->with(new CreateTable('orders', 1), new AddColumn('orders', new Column('b', 'string'), 2), $onUsers, $raw)
            ->build();

        $this->assertSame([$onUsers, $raw], $migration->operationsOnExistingTables(new DatabaseContext(Platform::unknown())));
    }

    public function testLeavesOutOperationsOnTablesCreatedByUnreleasedMigrations(): void
    {
        $onInvoices = new AddColumn('invoices', new Column('a', 'string'), 2);
        $onUsers = new AddColumn('users', new Column('b', 'string'), 3);
        $migration = MigrationBuilder::migration()->with($onInvoices, $onUsers)->build();

        $context = (new DatabaseContext(Platform::unknown()))->withNewTables(['invoices']);

        $this->assertSame([$onUsers], $migration->operationsOnExistingTables($context));
    }

    public function testNeverTreatsARuntimeTableNameAsNew(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(new CreateTable(Migration::DYNAMIC_TABLE, 1), new AddColumn(Migration::DYNAMIC_TABLE, new Column('a', 'string'), 2))
            ->build();

        $this->assertSame([], $migration->newTablesAfter([]));
        $this->assertCount(1, $migration->operationsOnExistingTables(new DatabaseContext(Platform::unknown())));
    }

    public function testKnowsTheTablesThatExistOnlyAfterIt(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(new CreateTable('drafts', 1), new RenameTable('invoices', 'bills', 2), new RenameTable('drafts', 'quotes', 3))
            ->build();

        $this->assertSame(['bills', 'quotes'], $migration->newTablesAfter(['invoices']));
    }

    public function testTellsSchemaChangesFromDataChanges(): void
    {
        $dataOnly = MigrationBuilder::migration()->with(new DataChange('users', 'update', 1), new RawStatement(null, 2))->build();
        $withSchema = MigrationBuilder::migration()->with(new CreateTable('orders', 1), new DataChange('users', 'update', 2))->build();

        $this->assertFalse($dataOnly->changesSchema());
        $this->assertTrue($withSchema->changesSchema());
        $this->assertCount(1, $withSchema->dataChangesOnExistingTables(new DatabaseContext(Platform::unknown())));
    }

    public function testDoesNotCountFillingATableItCreatesAsADataChange(): void
    {
        $migration = MigrationBuilder::migration()
            ->with(new CreateTable('countries', 1), new DataChange('countries', "DB::table('countries')->insert()", 5))
            ->build();

        $this->assertSame([], $migration->dataChangesOnExistingTables(new DatabaseContext(Platform::unknown())));
    }

    public function testAppliesSuppressionsToTheirRulesAndLines(): void
    {
        $migration = MigrationBuilder::migration()
            ->suppressing(new Suppression(10, 15, ['drop-column']))
            ->suppressing(new Suppression(30, 30, [Suppression::ALL_RULES]))
            ->build();

        $this->assertTrue($migration->isSuppressed('drop-column', 12));
        $this->assertFalse($migration->isSuppressed('drop-column', 16));
        $this->assertFalse($migration->isSuppressed('rename-column', 12));
        $this->assertTrue($migration->isSuppressed('rename-column', 30));
    }
}
