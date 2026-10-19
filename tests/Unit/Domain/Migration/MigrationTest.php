<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Migration;

use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DataChange;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
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

        $this->assertSame([$onUsers, $raw], $migration->operationsOnExistingTables());
    }

    public function testTellsSchemaChangesFromDataChanges(): void
    {
        $dataOnly = MigrationBuilder::migration()->with(new DataChange('users', 'update', 1), new RawStatement(null, 2))->build();
        $withSchema = MigrationBuilder::migration()->with(new CreateTable('orders', 1), new DataChange('users', 'update', 2))->build();

        $this->assertFalse($dataOnly->changesSchema());
        $this->assertTrue($withSchema->changesSchema());
        $this->assertCount(1, $withSchema->dataChanges());
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
