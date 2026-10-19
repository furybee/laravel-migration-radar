<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use Furybee\MigrationRadar\Domain\Migration\Operation\AddColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddForeignKey;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddIndex;
use Furybee\MigrationRadar\Domain\Migration\Operation\ChangeColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\Column;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropForeignKey;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropIndex;
use Furybee\MigrationRadar\Domain\Migration\Operation\IndexType;
use Furybee\MigrationRadar\Domain\Migration\Operation\Operation;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameTable;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Translates the body of a `Schema::table()` closure: `$table->...` calls become operations.
 */
final readonly class BlueprintInterpreter
{
    private const INDEX_METHODS = [
        'index' => IndexType::Index,
        'rawindex' => IndexType::Index,
        'unique' => IndexType::Unique,
        'primary' => IndexType::Primary,
        'fulltext' => IndexType::FullText,
        'spatialindex' => IndexType::Spatial,
    ];

    private const DROP_INDEX_METHODS = ['dropindex', 'dropunique', 'dropprimary', 'dropfulltext', 'dropspatialindex'];

    /**
     * Blueprint column types (Laravel 8 to 12), lowercase. Anything else is not a column:
     * a macro or an unknown method must not be reported as a NOT NULL column.
     */
    private const COLUMN_TYPES = [
        'bigincrements', 'biginteger', 'binary', 'boolean', 'char', 'computed', 'date', 'datetime', 'datetimetz',
        'decimal', 'double', 'enum', 'float', 'foreignid', 'foreignidfor', 'foreignulid', 'foreignuuid', 'geography',
        'geometry', 'geometrycollection', 'id', 'increments', 'integer', 'integerincrements', 'ipaddress', 'json', 'jsonb',
        'linestring', 'longtext', 'macaddress', 'mediumincrements', 'mediuminteger', 'mediumtext', 'multilinestring',
        'multipoint', 'multipolygon', 'multipolygonz', 'point', 'polygon', 'set', 'smallincrements', 'smallinteger',
        'string', 'text', 'time', 'timestamp', 'timestamptz', 'timetz', 'tinyincrements', 'tinyinteger', 'tinytext',
        'ulid', 'unsignedbiginteger', 'unsigneddecimal', 'unsigneddouble', 'unsignedfloat', 'unsignedinteger',
        'unsignedmediuminteger', 'unsignedsmallinteger', 'unsignedtinyinteger', 'uuid', 'vector', 'year',
    ];

    /** Column types whose name argument is optional: type => default name */
    private const DEFAULT_COLUMN_NAMES = ['id' => 'id', 'uuid' => 'uuid', 'ulid' => 'ulid', 'ipaddress' => 'ip_address', 'macaddress' => 'mac_address'];

    /** Column helpers without a name argument: method => [default column names, nullable] */
    private const COLUMN_SHORTCUTS = [
        'timestamps' => [['created_at', 'updated_at'], true],
        'timestampstz' => [['created_at', 'updated_at'], true],
        'nullabletimestamps' => [['created_at', 'updated_at'], true],
        'softdeletes' => [['deleted_at'], true],
        'softdeletestz' => [['deleted_at'], true],
        'remembertoken' => [['remember_token'], true],
    ];

    /** Polymorphic helpers: method => nullable */
    private const MORPH_METHODS = [
        'morphs' => false, 'uuidmorphs' => false, 'ulidmorphs' => false, 'numericmorphs' => false,
        'nullablemorphs' => true, 'nullableuuidmorphs' => true, 'nullableulidmorphs' => true, 'nullablenumericmorphs' => true,
    ];

    public function __construct(private StringResolver $strings)
    {
    }

    /**
     * @param Expr\Closure|Expr\ArrowFunction $closure the second argument of `Schema::table()`
     * @param bool $isPositioned the closure is an `after('column', ...)` group: its columns are placed
     * @return list<Operation>
     */
    public function interpret(string $table, Expr\Closure|Expr\ArrowFunction $closure, bool $isPositioned = false): array
    {
        $blueprint = $closure->params[0]->var ?? null;
        if (!$blueprint instanceof Expr\Variable || !is_string($blueprint->name)) {
            return [];
        }

        $operations = [];
        foreach ($this->blueprintChains($closure, $blueprint->name) as $chain) {
            array_push($operations, ...$this->operationsOf($table, $chain, $isPositioned));
        }

        return $operations;
    }

    /**
     * Chains on the blueprint in this closure, conditions and loops included.
     * The body of an `after()` group is left out: the group is interpreted on its own, as positioned.
     *
     * @return list<CallChain>
     */
    private function blueprintChains(Expr\Closure|Expr\ArrowFunction $closure, string $blueprint): array
    {
        if ($closure instanceof Expr\ArrowFunction) {
            $expressions = [$closure->expr];
        } else {
            $finder = new NodeFinder();
            $groupStatements = new \SplObjectStorage();
            foreach ($finder->findInstanceOf($closure->stmts, Expr\MethodCall::class) as $call) {
                $group = $call->args[1] ?? null;
                $isAfterGroup = $call->name instanceof Node\Identifier && strtolower($call->name->toString()) === 'after'
                    && $group instanceof Node\Arg && $group->value instanceof Expr\Closure;
                if ($isAfterGroup) {
                    foreach ($finder->findInstanceOf([$group->value], Node\Stmt\Expression::class) as $statement) {
                        $groupStatements->attach($statement);
                    }
                }
            }

            $expressions = [];
            foreach ($finder->findInstanceOf($closure->stmts, Node\Stmt\Expression::class) as $statement) {
                if (!$groupStatements->contains($statement)) {
                    $expressions[] = $statement->expr;
                }
            }
        }

        $chains = [];
        foreach ($expressions as $expression) {
            $chain = CallChain::fromExpression($expression);
            if ($chain !== null && $chain->isRootVariable($blueprint)) {
                $chains[] = $chain;
            }
        }

        return $chains;
    }

    /**
     * @return list<Operation>
     */
    private function operationsOf(string $table, CallChain $chain, bool $isPositioned): array
    {
        $call = $chain->first();
        $method = strtolower($call->name);

        return match (true) {
            $method === 'after' => $this->afterGroup($table, $call),
            $method === 'rename' => $this->renameTable($table, $call),
            str_starts_with($method, 'drop') => $this->dropOperations($table, $method, $call),
            $method === 'renamecolumn' => $this->renameColumn($table, $call),
            isset(self::INDEX_METHODS[$method]) => [new AddIndex($table, self::INDEX_METHODS[$method], $this->strings->resolveList($call->argument(0)), $call->line)],
            $method === 'foreign' => [new AddForeignKey($table, $this->strings->resolveList($call->argument(0)), $this->strings->resolve($chain->find('on')?->argument(0)), $call->line)],
            isset(self::COLUMN_SHORTCUTS[$method]) => $this->shortcutColumns($table, $method, $call),
            isset(self::MORPH_METHODS[$method]) => $this->morphColumns($table, $method, $call),
            in_array($method, self::COLUMN_TYPES, true) => $this->columnOperations($table, $chain, $isPositioned),
            default => [],
        };
    }

    /**
     * `$table->after('email', function (Blueprint $table) { ... })`: every column of the group is placed.
     *
     * @return list<Operation>
     */
    private function afterGroup(string $table, Call $call): array
    {
        $group = $call->argument(1);

        return $group instanceof Expr\Closure || $group instanceof Expr\ArrowFunction
            ? $this->interpret($table, $group, isPositioned: true)
            : [];
    }

    /**
     * @return list<Operation>
     */
    private function renameTable(string $table, Call $call): array
    {
        $to = $this->strings->resolve($call->argument(0));

        return $to === null ? [] : [new RenameTable($table, $to, $call->line)];
    }

    /**
     * @return list<Operation>
     */
    private function dropOperations(string $table, string $method, Call $call): array
    {
        $columns = match ($method) {
            'dropcolumn', 'dropcolumns' => array_merge(...array_map(fn(Node\Arg $arg) => $this->strings->resolveList($arg->value), $call->args)),
            'droptimestamps', 'droptimestampstz' => ['created_at', 'updated_at'],
            'dropsoftdeletes', 'dropsoftdeletestz' => [$this->strings->resolve($call->argument(0)) ?? 'deleted_at'],
            'dropremembertoken' => ['remember_token'],
            'dropmorphs' => $this->morphColumnNames($call),
            'dropconstrainedforeignid' => $this->strings->resolveList($call->argument(0)),
            default => [],
        };

        $operations = array_map(static fn(string $column) => new DropColumn($table, $column, $call->line), $columns);

        if ($method === 'dropforeign' || $method === 'dropconstrainedforeignid') {
            array_unshift($operations, new DropForeignKey($table, $call->line));
        }
        if (in_array($method, self::DROP_INDEX_METHODS, true)) {
            $operations[] = new DropIndex($table, $call->line);
        }

        return $operations;
    }

    /**
     * @return list<Operation>
     */
    private function renameColumn(string $table, Call $call): array
    {
        $from = $this->strings->resolve($call->argument(0));
        $to = $this->strings->resolve($call->argument(1));

        return $from === null || $to === null ? [] : [new RenameColumn($table, $from, $to, $call->line)];
    }

    /**
     * @return list<Operation>
     */
    private function shortcutColumns(string $table, string $method, Call $call): array
    {
        [$names, $isNullable] = self::COLUMN_SHORTCUTS[$method];
        $customName = $this->strings->resolve($call->argument(0));
        if ($customName !== null && count($names) === 1) {
            $names = [$customName];
        }

        return array_map(
            static fn(string $name) => new AddColumn($table, new Column($name, $method, isNullable: $isNullable), $call->line),
            $names,
        );
    }

    /**
     * @return list<Operation>
     */
    private function morphColumns(string $table, string $method, Call $call): array
    {
        $names = $this->morphColumnNames($call);
        $operations = array_map(
            static fn(string $name) => new AddColumn($table, new Column($name, $method, isNullable: self::MORPH_METHODS[$method]), $call->line),
            $names,
        );
        if ($names !== []) {
            $operations[] = new AddIndex($table, IndexType::Index, $names, $call->line);
        }

        return $operations;
    }

    /**
     * @return list<string>
     */
    private function morphColumnNames(Call $call): array
    {
        $name = $this->strings->resolve($call->argument(0));

        return $name === null ? [] : ["{$name}_type", "{$name}_id"];
    }

    /**
     * `$table->string('name')->nullable()->index()->change()`: a column definition with its modifiers.
     *
     * @return list<Operation>
     */
    private function columnOperations(string $table, CallChain $chain, bool $isInPositionedGroup): array
    {
        $definition = $chain->first();
        $type = strtolower($definition->name);
        $name = $this->columnName($definition);
        if ($name === null) {
            return [];
        }

        $isIncrementing = $type === 'id' || str_ends_with($type, 'increments');
        $column = new Column(
            $name,
            $definition->name,
            isNullable: $this->isNullable($chain),
            hasDefault: $chain->find('default', 'useCurrent') !== null,
            hasExpressionDefault: $this->isExpression($chain->find('default')?->argument(0)),
            isPositioned: $isInPositionedGroup || $chain->find('after', 'first') !== null,
            isStoredGenerated: $chain->find('storedAs') !== null,
            isAutoIncrement: $isIncrementing || $chain->find('autoIncrement') !== null,
            isVirtualGenerated: $chain->find('virtualAs') !== null,
        );

        if ($chain->find('change') !== null) {
            return [new ChangeColumn($table, $column, $definition->line)];
        }

        $operations = [new AddColumn($table, $column, $definition->line)];

        // id() and *increments() columns are the primary key
        if ($isIncrementing) {
            $operations[] = new AddIndex($table, IndexType::Primary, [$name], $definition->line);
        }

        foreach (self::INDEX_METHODS as $modifier => $indexType) {
            if ($chain->find($modifier) !== null) {
                $operations[] = new AddIndex($table, $indexType, [$name], $definition->line);
            }
        }

        $constrained = $chain->find('constrained');
        $on = $chain->find('on');
        if ($constrained !== null || $on !== null) {
            $referencedTable = $this->strings->resolve(($constrained ?? $on)->argument(0));
            $operations[] = new AddForeignKey($table, [$name], $referencedTable, $definition->line);
        }

        return $operations;
    }

    private function columnName(Call $definition): ?string
    {
        $type = strtolower($definition->name);
        $argument = $definition->argument(0);

        // foreignIdFor(User::class) names the column after the model (user_id), unless a name is given
        if ($type === 'foreignidfor') {
            return $this->strings->resolve($definition->argument(1)) ?? $this->modelForeignKey($argument);
        }

        return $this->strings->resolve($argument) ?? self::DEFAULT_COLUMN_NAMES[$type] ?? null;
    }

    private function modelForeignKey(?Expr $model): ?string
    {
        if (!$model instanceof Expr\ClassConstFetch || !$model->class instanceof Node\Name || !$model->name instanceof Node\Identifier || strtolower($model->name->toString()) !== 'class') {
            return null;
        }

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $model->class->getLast())) . '_id';
    }

    private function isNullable(CallChain $chain): bool
    {
        $nullable = $chain->find('nullable');
        if ($nullable === null) {
            return false;
        }

        $argument = $nullable->argument(0);

        return !($argument instanceof Expr\ConstFetch && strtolower($argument->name->toString()) === 'false');
    }

    /**
     * `DB::raw('...')` or `new Expression('...')`: evaluated by the database, maybe for each row.
     */
    private function isExpression(?Expr $default): bool
    {
        return ($default instanceof Expr\StaticCall && $default->name instanceof Node\Identifier && strtolower($default->name->toString()) === 'raw')
            || ($default instanceof Expr\New_ && $default->class instanceof Node\Name && $default->class->getLast() === 'Expression');
    }
}
