<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Infrastructure\Parser;

use Furybee\MigrationRadar\Application\Port\MigrationNotParsable;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddForeignKey;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddIndex;
use Furybee\MigrationRadar\Domain\Migration\Operation\ChangeColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DataChange;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropForeignKey;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropIndex;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\IndexType;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameTable;
use Furybee\MigrationRadar\Domain\Migration\Suppression;
use Furybee\MigrationRadar\Infrastructure\Parser\LaravelMigrationParser;
use PHPUnit\Framework\TestCase;

final class LaravelMigrationParserTest extends TestCase
{
    private LaravelMigrationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LaravelMigrationParser(dirname(__DIR__, 3) . '/Fixtures');
    }

    public function testReadsColumnDefinitionsAndTheirModifiers(): void
    {
        $migration = $this->parser->parse('migrations/add_columns.php');

        $this->assertSame('migrations/add_columns.php', $migration->path);
        $this->assertEquals([
            new CreateTable('orders', 12),
            new AddColumn('users', new Column('code', 'string'), 19),
            new AddColumn('users', new Column('nickname', 'string', isNullable: true, isPositioned: true), 20),
            new AddColumn('users', new Column('is_active', 'boolean', hasDefault: true), 21),
            new AddColumn('users', new Column('public_id', 'uuid', hasDefault: true, hasExpressionDefault: true), 22),
            new AddColumn('users', new Column('seen_at', 'timestamp', hasDefault: true), 23),
            new AddColumn('users', new Column('total', 'integer', isStoredGenerated: true), 24),
            new AddColumn('users', new Column('email', 'string'), 25),
            new AddIndex('users', IndexType::Unique, ['email'], 25),
            new AddColumn('users', new Column('company_id', 'foreignId', isNullable: true), 26),
            new AddForeignKey('users', ['company_id'], 'companies', 26),
            new AddColumn('users', new Column('team_id', 'foreignIdFor'), 27),
            new AddColumn('users', new Column('created_at', 'timestamps', isNullable: true), 28),
            new AddColumn('users', new Column('updated_at', 'timestamps', isNullable: true), 28),
            new AddColumn('users', new Column('archived_at', 'softdeletes', isNullable: true), 29),
            new AddColumn('users', new Column('owner_type', 'nullablemorphs', isNullable: true), 30),
            new AddColumn('users', new Column('owner_id', 'nullablemorphs', isNullable: true), 30),
            new AddIndex('users', IndexType::Index, ['owner_type', 'owner_id'], 30),
        ], $migration->operations);
    }

    public function testReadsChangesDropsIndexesAndForeignKeys(): void
    {
        $migration = $this->parser->parse('migrations/change_and_drop.php');

        $this->assertEquals([
            new ChangeColumn('users', new Column('email', 'string'), 11),
            new RenameColumn('users', 'name', 'full_name', 12),
            new DropColumn('users', 'legacy', 13),
            new DropColumn('users', 'old', 13),
            new DropColumn('users', 'created_at', 14),
            new DropColumn('users', 'updated_at', 14),
            new DropForeignKey('users', 15),
            new DropIndex('users', 16),
            new DropForeignKey('users', 17),
            new DropColumn('users', 'team_id', 17),
            new AddIndex('users', IndexType::Index, ['last_name', 'first_name'], 18),
            new AddIndex('users', IndexType::FullText, ['bio'], 19),
            new AddForeignKey('users', ['owner_id'], 'accounts', 20),
        ], $migration->operations);
    }

    public function testResolvesTableNamesAndIgnoresDown(): void
    {
        $migration = $this->parser->parse('migrations/schema_facade.php');

        $this->assertEquals([
            new RenameTable('bills', 'invoices', 13),
            new DropTable('billing_drafts', 14),
            new DropTable('payments', 15),
            new DropColumn('invoices', 'notes', 16),
            new DropColumn('stats', 'views', 17),
            new DropColumn('(dynamic)', 'tmp', 21),
        ], $migration->operations);
    }

    public function testReadsRawSqlAndDataChanges(): void
    {
        $migration = $this->parser->parse('migrations/data_changes.php');

        $this->assertEquals([
            new RawStatement('ALTER TABLE users ADD COLUMN x INT', 10),
            new RawStatement(null, 11),
            new DataChange('users', "DB::table('users')->update()", 12),
            new DataChange(null, 'User::...->delete()', 13),
            new DataChange(null, 'User::...->chunkById()', 14),
            new DataChange(null, 'DB::update()', 15),
        ], $migration->operations);
    }

    public function testReadsIgnoreComments(): void
    {
        $migration = $this->parser->parse('migrations/suppressed.php');

        $this->assertEquals([
            new Suppression(10, 14, ['rename-column', 'drop-column']),
            new Suppression(16, 17, [Suppression::ALL_RULES]),
        ], $migration->suppressions);
    }

    public function testReadsNamedMigrationClasses(): void
    {
        $this->assertEquals([new DropTable('legacy', 10)], $this->parser->parse('migrations/named_class.php')->operations);
    }

    public function testReturnsNoOperationWithoutUpMethod(): void
    {
        $this->assertSame([], $this->parser->parse('migrations/down_only.php')->operations);
    }

    public function testRejectsFilesItCannotRead(): void
    {
        foreach (['migrations/syntax_error.php', 'migrations/not_a_migration.php', 'migrations/missing.php'] as $file) {
            try {
                $this->parser->parse($file);
                $this->fail("{$file} should not be parsable");
            } catch (MigrationNotParsable $e) {
                $this->assertStringStartsWith($file, $e->getMessage());
            }
        }
    }
}
