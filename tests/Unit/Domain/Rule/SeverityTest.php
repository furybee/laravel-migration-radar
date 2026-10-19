<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Domain\Rule;

use Furybee\MigrationRadar\Domain\Rule\Severity;
use PHPUnit\Framework\TestCase;

final class SeverityTest extends TestCase
{
    public function testOrdersSeverities(): void
    {
        $this->assertTrue(Severity::Error->isAtLeast(Severity::Warning));
        $this->assertTrue(Severity::Warning->isAtLeast(Severity::Warning));
        $this->assertFalse(Severity::Info->isAtLeast(Severity::Warning));
    }

    public function testDowngradesErrorsOnUnknownPlatforms(): void
    {
        $this->assertSame(Severity::Warning, Severity::Error->onPlatformKnown(false));
        $this->assertSame(Severity::Error, Severity::Error->onPlatformKnown(true));
        $this->assertSame(Severity::Info, Severity::Info->onPlatformKnown(false));
    }
}
