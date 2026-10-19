<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Application;

use Furybee\MigrationRadar\Application\Analysis\PathSuppressions;
use Furybee\MigrationRadar\Application\Analysis\RuleSet;
use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;
use PHPUnit\Framework\TestCase;

final class RuleSetTest extends TestCase
{
    public function testHasUniqueKebabCaseIds(): void
    {
        $ids = RuleSet::default()->ids();

        $this->assertSame($ids, array_values(array_unique($ids)));
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*$/', $id);
        }
    }

    public function testDisablesRules(): void
    {
        $rules = RuleSet::default()->without(['raw-sql', 'drop-table']);

        $this->assertNotContains('raw-sql', $rules->ids());
        $this->assertCount(count(RuleSet::default()->rules) - 2, $rules->rules);
    }

    public function testRejectsUnknownRuleIds(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('raw-sqll');

        RuleSet::default()->without(['raw-sqll']);
    }

    public function testMatchesIgnoredPathsWithGlobs(): void
    {
        $suppressions = new PathSuppressions(['database/migrations/2019_*' => ['*'], './legacy/*.php' => ['drop-column']]);

        $this->assertTrue($suppressions->covers('database/migrations/2019_01_01_init.php', 'rename-column'));
        $this->assertTrue($suppressions->covers('legacy/old.php', 'drop-column'));
        $this->assertFalse($suppressions->covers('legacy/old.php', 'rename-column'));
        $this->assertFalse($suppressions->covers('database/migrations/2020_01_01_init.php', 'rename-column'));
    }
}
