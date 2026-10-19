<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DataChange;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\Operation;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameTable;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Walks the body of `up()`: schema facade calls, raw SQL and data changes.
 */
final class UpMethodInterpreter extends NodeVisitorAbstract
{

    private const RAW_SQL_METHODS = ['statement', 'unprepared'];
    private const RAW_DATA_METHODS = ['insert', 'update', 'delete', 'affectingstatement'];
    private const WRITE_METHODS = [
        'insert', 'insertorignore', 'insertgetid', 'insertusing', 'upsert', 'update', 'updateorinsert', 'updateorcreate',
        'increment', 'decrement', 'incrementeach', 'decrementeach', 'delete', 'forcedelete', 'truncate',
        'create', 'forcecreate', 'firstorcreate', 'restore',
    ];
    private const ITERATE_METHODS = ['chunk', 'chunkbyid', 'chunkbyiddesc', 'each', 'eachbyid', 'lazy', 'lazybyid', 'cursor'];
    private const SELF_REFERENCES = ['self', 'static', 'parent'];

    /** @var list<Operation> */
    private array $operations = [];

    public function __construct(
        private readonly StringResolver $strings,
        private readonly BlueprintInterpreter $blueprints,
    ) {
    }

    /**
     * @param array<Node\Stmt> $statements body of up()
     * @return list<Operation>
     */
    public function interpret(array $statements): array
    {
        $this->operations = [];
        (new NodeTraverser($this))->traverse($statements);

        return $this->operations;
    }

    public function enterNode(Node $node): ?int
    {
        if (!$node instanceof Expr\StaticCall && !$node instanceof Expr\MethodCall && !$node instanceof Expr\NullsafeMethodCall) {
            return null;
        }

        $chain = CallChain::fromExpression($node);
        $class = $chain?->rootClass();
        if ($chain === null || $class === null || in_array(strtolower($class), self::SELF_REFERENCES, true)) {
            return null;
        }

        match (self::shortName($class)) {
            'Schema' => $this->schemaCall($chain),
            'DB' => $this->databaseCall($chain),
            default => $this->modelCall($class, $chain),
        };

        return null;
    }

    /**
     * Children are still visited: wrappers such as `Schema::withoutForeignKeyConstraints()` hold schema calls,
     * and Blueprint closures may run `DB::` queries. `$table->...` calls are only read by the BlueprintInterpreter.
     */
    private function schemaCall(CallChain $chain): void
    {
        $call = self::skipConnection($chain);
        if ($call === null) {
            return;
        }

        $table = $this->strings->resolve($call->argument(0)) ?? Migration::DYNAMIC_TABLE;
        $closure = $call->argument(1);

        array_push($this->operations, ...match (strtolower($call->name)) {
            'create' => [new CreateTable($table, $call->line)],
            'table' => $closure instanceof Expr\Closure || $closure instanceof Expr\ArrowFunction
                ? $this->blueprints->interpret($table, $closure)
                : [],
            'drop', 'dropifexists' => [new DropTable($table, $call->line)],
            'rename' => [new RenameTable($table, $this->strings->resolve($call->argument(1)) ?? Migration::DYNAMIC_TABLE, $call->line)],
            'dropcolumns' => array_map(
                static fn(string $column) => new DropColumn($table, $column, $call->line),
                $this->strings->resolveList($call->argument(1)),
            ),
            default => [],
        });
    }

    private function databaseCall(CallChain $chain): void
    {
        $first = self::skipConnection($chain);
        if ($first === null) {
            return;
        }
        $method = strtolower($first->name);
        $isSingleCall = $chain->last() === $first;

        if ($isSingleCall && in_array($method, self::RAW_SQL_METHODS, true)) {
            $this->operations[] = new RawStatement($this->strings->resolve($first->argument(0)), $first->line);
        } elseif ($isSingleCall && in_array($method, self::RAW_DATA_METHODS, true)) {
            $this->operations[] = new DataChange(null, "DB::{$first->name}()", $first->line);
        } elseif ($method === 'table' && $this->isDataChange($chain->last()->name)) {
            $table = $this->strings->resolve($first->argument(0)) ?? Migration::DYNAMIC_TABLE;
            $this->operations[] = new DataChange($table, "DB::table('{$table}')->{$chain->last()->name}()", $chain->last()->line);
        }
    }

    private function modelCall(string $class, CallChain $chain): void
    {
        if ($this->isDataChange($chain->last()->name)) {
            $this->operations[] = new DataChange(null, self::shortName($class) . "::...->{$chain->last()->name}()", $chain->last()->line);
        }
    }

    private function isDataChange(string $method): bool
    {
        $method = strtolower($method);

        return in_array($method, self::WRITE_METHODS, true) || in_array($method, self::ITERATE_METHODS, true);
    }

    /**
     * `Schema::connection('x')->table(...)`, `DB::connection('x')->statement(...)`: the call after the connection.
     */
    private static function skipConnection(CallChain $chain): ?Call
    {
        return strtolower($chain->first()->name) === 'connection' ? ($chain->calls[1] ?? null) : $chain->first();
    }

    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
