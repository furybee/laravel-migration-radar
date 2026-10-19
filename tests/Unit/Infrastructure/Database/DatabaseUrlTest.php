<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Infrastructure\Database;

use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;
use Furybee\MigrationRadar\Infrastructure\Database\DatabaseUrl;
use PHPUnit\Framework\TestCase;

final class DatabaseUrlTest extends TestCase
{
    public function testConvertsAMySqlUrlWithEncodedCredentials(): void
    {
        $url = DatabaseUrl::parse('mysql://radar:p%40ss%3Aword@db.internal/app');

        $this->assertSame('mysql:host=db.internal;port=3306;dbname=app', $url->pdoDsn);
        $this->assertSame('radar', $url->user);
        $this->assertSame('p@ss:word', $url->password);
    }

    public function testConvertsPostgreSqlAliasesAndPorts(): void
    {
        $this->assertSame('pgsql:host=localhost;port=6432;dbname=app', DatabaseUrl::parse('postgresql://u@localhost:6432/app')->pdoDsn);
        $this->assertSame('pgsql:host=localhost;port=5432;dbname=app', DatabaseUrl::parse('postgres://localhost/app')->pdoDsn);
    }

    public function testConvertsSqlitePaths(): void
    {
        $this->assertSame('sqlite:/var/data/app.sqlite', DatabaseUrl::parse('sqlite:///var/data/app.sqlite')->pdoDsn);
    }

    public function testRejectsUnsupportedOrIncompleteUrls(): void
    {
        foreach (['oracle://db/app', 'mysql://only-host', 'not a url'] as $invalid) {
            try {
                DatabaseUrl::parse($invalid);
                $this->fail("{$invalid} should be rejected");
            } catch (InvalidConfiguration) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
