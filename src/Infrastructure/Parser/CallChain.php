<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * A fluent call chain such as `$table->string('name')->nullable()->change()`,
 * split into its receiver (`$table`) and its calls, innermost first.
 */
final readonly class CallChain
{
    /**
     * @param list<Call> $calls innermost first
     */
    private function __construct(
        public Expr|Node\Name $root,
        public array $calls,
    ) {
    }

    public static function fromExpression(Expr $expression): ?self
    {
        $calls = [];
        $current = $expression;

        while (true) {
            if (($current instanceof Expr\MethodCall || $current instanceof Expr\NullsafeMethodCall) && $current->name instanceof Node\Identifier) {
                $calls[] = self::call($current->name->toString(), $current);
                $current = $current->var;
                continue;
            }

            if ($current instanceof Expr\StaticCall && $current->name instanceof Node\Identifier) {
                $calls[] = self::call($current->name->toString(), $current);
                $current = $current->class;
                break;
            }

            break;
        }

        if ($calls === []) {
            return null;
        }

        return new self($current, array_reverse($calls));
    }

    public function first(): Call
    {
        return $this->calls[0];
    }

    public function last(): Call
    {
        return $this->calls[count($this->calls) - 1];
    }

    public function find(string ...$names): ?Call
    {
        foreach ($this->calls as $call) {
            if (in_array(strtolower($call->name), array_map('strtolower', $names), true)) {
                return $call;
            }
        }

        return null;
    }

    public function isRootVariable(string $name): bool
    {
        return $this->root instanceof Expr\Variable && $this->root->name === $name;
    }

    /**
     * Static receiver class, `Schema` for `Schema::table()`; null for an instance receiver.
     */
    public function rootClass(): ?string
    {
        return $this->root instanceof Node\Name ? $this->root->toString() : null;
    }

    private static function call(string $name, Expr\MethodCall|Expr\NullsafeMethodCall|Expr\StaticCall $node): Call
    {
        $args = array_values(array_filter($node->args, static fn($arg) => $arg instanceof Node\Arg));

        return new Call($name, $args, $node->getStartLine());
    }
}
