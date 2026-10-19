<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Database;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Platform;
use PHPUnit\Framework\TestCase;

final class DatabaseContextTest extends TestCase
{
    public function testComparesKnownTablesWithTheThreshold(): void
    {
        $context = new DatabaseContext(Platform::unknown(), ['users' => 2_000, 'logs' => 999], largeTableRows: 1_000);

        $this->assertTrue($context->isLargeTable('users'));
        $this->assertFalse($context->isLargeTable('logs'));
    }

    public function testAssumesUnknownTablesAreLargeUnlessToldOtherwise(): void
    {
        $this->assertTrue((new DatabaseContext(Platform::unknown()))->isLargeTable('users'));
        $this->assertFalse((new DatabaseContext(Platform::unknown(), assumeLargeWhenUnknown: false))->isLargeTable('users'));
    }

    public function testDescribesTheTableSizeWhenKnown(): void
    {
        $context = new DatabaseContext(Platform::unknown(), ['users' => 1_234_567]);

        $this->assertSame('"users" (~1,234,567 rows)', $context->describeTableSize('users'));
        $this->assertSame('"logs"', $context->describeTableSize('logs'));
    }
}
