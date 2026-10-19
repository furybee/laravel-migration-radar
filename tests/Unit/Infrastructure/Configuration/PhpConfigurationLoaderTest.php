<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Infrastructure\Configuration;

use Furybee\MigrationRadar\Application\Configuration\Configuration;
use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;
use Furybee\MigrationRadar\Infrastructure\Configuration\PhpConfigurationLoader;
use Furybee\MigrationRadar\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpConfigurationLoaderTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testReadsEveryKey(): void
    {
        $file = $this->configFile(<<<'PHP'
            [
                'paths' => ['database/migrations', 'modules/billing/migrations'],
                'platform' => 'mysql:8.0.35',
                'dsn' => 'mysql://radar@db/app',
                'large_table_rows' => 500_000,
                'assume_large_tables' => false,
                'table_rows' => ['users' => 2_000_000],
                'disabled_rules' => ['raw-sql'],
                'ignore' => ['database/migrations/2019_*' => ['*']],
                'table_prefix' => 'app_',
            ]
            PHP);

        $this->assertEquals(new Configuration(
            ['database/migrations', 'modules/billing/migrations'],
            'mysql:8.0.35',
            'mysql://radar@db/app',
            500_000,
            false,
            ['users' => 2_000_000],
            ['raw-sql'],
            ['database/migrations/2019_*' => ['*']],
            'app_',
        ), (new PhpConfigurationLoader())->load($file));
    }

    public function testFallsBackToDefaults(): void
    {
        $this->assertEquals(new Configuration(), (new PhpConfigurationLoader())->load($this->configFile('[]')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'not an array' => ['"mysql"', 'must return an array'];
        yield 'typo in a key' => ["['plateform' => 'mysql']", 'Unknown configuration key(s): plateform'];
        yield 'paths not strings' => ["['paths' => [1]]", '"paths" must be a list of strings'];
        yield 'threshold not positive' => ["['large_table_rows' => 0]", '"large_table_rows" must be a positive integer'];
        yield 'flag not boolean' => ["['assume_large_tables' => 'no']", '"assume_large_tables" must be a boolean'];
        yield 'row count not integer' => ["['table_rows' => ['users' => '1M']]", '"table_rows" must map table names to row counts'];
        yield 'ignore without rules' => ["['ignore' => ['a/*' => [1]]]", '"ignore.a/*" must be a list of strings'];
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidValuesWithAClearMessage(string $values, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage($message);

        (new PhpConfigurationLoader())->load($this->configFile($values));
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(InvalidConfiguration::class);

        (new PhpConfigurationLoader())->load($this->directory->path . '/missing.php');
    }

    private function configFile(string $values): string
    {
        return $this->directory->write('migration-radar.php', "<?php\n\nreturn {$values};\n");
    }
}
