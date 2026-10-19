<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Evaluates the string expressions migrations use for names: literals, class constants, concatenations.
 */
final readonly class StringResolver
{
    /**
     * @param array<string, string> $classConstants constants of the migration class
     */
    public function __construct(private array $classConstants = [])
    {
    }

    public function resolve(?Expr $expression): ?string
    {
        return match (true) {
            $expression instanceof Scalar\String_ => $expression->value,
            $expression instanceof Scalar\Int_ => (string) $expression->value,
            $expression instanceof Expr\BinaryOp\Concat => $this->concat($expression),
            $expression instanceof Expr\ClassConstFetch => $this->classConstant($expression),
            default => null,
        };
    }

    /**
     * Strings and arrays of strings: `dropColumn('a')`, `dropColumn(['a', 'b'])`.
     *
     * @return list<string>
     */
    public function resolveList(?Expr $expression): array
    {
        if (!$expression instanceof Expr\Array_) {
            $value = $this->resolve($expression);

            return $value === null ? [] : [$value];
        }

        $values = [];
        foreach ($expression->items as $item) {
            $value = $this->resolve($item->value);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function concat(Expr\BinaryOp\Concat $expression): ?string
    {
        $left = $this->resolve($expression->left);
        $right = $this->resolve($expression->right);

        return $left === null || $right === null ? null : $left . $right;
    }

    private function classConstant(Expr\ClassConstFetch $fetch): ?string
    {
        if (!$fetch->name instanceof Node\Identifier || !$fetch->class instanceof Node\Name) {
            return null;
        }

        $isOwnClass = in_array(strtolower($fetch->class->toString()), ['self', 'static'], true);

        return $isOwnClass ? $this->classConstants[$fetch->name->toString()] ?? null : null;
    }
}
