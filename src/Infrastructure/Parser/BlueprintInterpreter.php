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
        'unique' => IndexType::Unique,
        'primary' => IndexType::Primary,
        'fulltext' => IndexType::FullText,
        'spatialindex' => IndexType::Spatial,
    ];

    private const DROP_INDEX_METHODS = ['dropindex', 'dropunique', 'dropprimary', 'dropfulltext', 'dropspatialindex'];

    /** Blueprint calls that change nothing worth analysing */
    private const IGNORED_METHODS = ['engine', 'charset', 'collation', 'comment', 'temporary', 'renameindex', 'after'];

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
     * @return list<Operation>
     */
    public function interpret(string $table, Expr\Closure|Expr\ArrowFunction $closure): array
    {
        $blueprint = $closure->params[0]->var ?? null;
        if (!$blueprint instanceof Expr\Variable || !is_string($blueprint->name)) {
            return [];
        }

        $operations = [];
        foreach ($this->blueprintChains($closure, $blueprint->name) as $chain) {
            array_push($operations, ...$this->operationsOf($table, $chain));
        }

        return $operations;
    }

    /**
     * @return list<CallChain>
     */
    private function blueprintChains(Expr\Closure|Expr\ArrowFunction $closure, string $blueprint): array
    {
        $expressions = $closure instanceof Expr\ArrowFunction
            ? [$closure->expr]
            : array_map(
                static fn(Node\Stmt\Expression $statement) => $statement->expr,
                (new NodeFinder())->findInstanceOf($closure->stmts, Node\Stmt\Expression::class),
            );

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
    private function operationsOf(string $table, CallChain $chain): array
    {
        $call = $chain->first();
        $method = strtolower($call->name);

        return match (true) {
            in_array($method, self::IGNORED_METHODS, true) => [],
            str_starts_with($method, 'drop') => $this->dropOperations($table, $method, $call),
            $method === 'renamecolumn' => $this->renameColumn($table, $call),
            isset(self::INDEX_METHODS[$method]) => [new AddIndex($table, self::INDEX_METHODS[$method], $this->strings->resolveList($call->argument(0)), $call->line)],
            $method === 'foreign' => [new AddForeignKey($table, $this->strings->resolveList($call->argument(0)), $this->strings->resolve($chain->find('on')?->argument(0)), $call->line)],
            isset(self::COLUMN_SHORTCUTS[$method]) => $this->shortcutColumns($table, $method, $call),
            isset(self::MORPH_METHODS[$method]) => $this->morphColumns($table, $method, $call),
            default => $this->columnOperations($table, $chain),
        };
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
    private function columnOperations(string $table, CallChain $chain): array
    {
        $definition = $chain->first();
        $name = $this->columnName($definition);
        if ($name === null) {
            return [];
        }

        $column = new Column(
            $name,
            $definition->name,
            isNullable: $this->isNullable($chain),
            hasDefault: $chain->find('default', 'useCurrent') !== null,
            hasExpressionDefault: $this->isExpression($chain->find('default')?->argument(0)),
            isPositioned: $chain->find('after', 'first') !== null,
            isStoredGenerated: $chain->find('storedAs') !== null,
            isAutoIncrement: $chain->find('autoIncrement') !== null || str_contains(strtolower($definition->name), 'increments') || strtolower($definition->name) === 'id',
        );

        $operations = [$chain->find('change') === null
            ? new AddColumn($table, $column, $definition->line)
            : new ChangeColumn($table, $column, $definition->line)];

        foreach (self::INDEX_METHODS as $modifier => $type) {
            if ($chain->find($modifier) !== null) {
                $operations[] = new AddIndex($table, $type, [$name], $definition->line);
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
        $argument = $definition->argument(0);
        if (strtolower($definition->name) === 'id') {
            return $this->strings->resolve($argument) ?? 'id';
        }

        // foreignIdFor(User::class) names the column after the model: user_id
        if ($argument instanceof Expr\ClassConstFetch && $argument->class instanceof Node\Name && $argument->name instanceof Node\Identifier && strtolower($argument->name->toString()) === 'class') {
            return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $argument->class->getLast())) . '_id';
        }

        return $this->strings->resolve($argument);
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
