<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use Furybee\MigrationRadar\Domain\Migration\Suppression;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Reads `// migration-radar-ignore rule-a, rule-b -- reason` comments; without rule ids they cover every rule.
 * On its own line, a comment covers the statement it precedes, closures included.
 * After code, it only covers its own line: php-parser attaches it to the next statement, which it must not hide.
 */
final class SuppressionCollector
{
    public const MARKER = 'migration-radar-ignore';

    /**
     * @param array<Node> $ast
     * @return list<Suppression>
     */
    public function collect(array $ast, string $code): array
    {
        $suppressions = [];
        foreach ((new NodeFinder())->findInstanceOf($ast, Node\Stmt::class) as $statement) {
            foreach ($statement->getComments() as $comment) {
                $ruleIds = self::ruleIds($comment->getText());
                if ($ruleIds === null) {
                    continue;
                }

                $lastLine = self::followsCode($code, $comment->getStartFilePos()) ? $comment->getStartLine() : $statement->getEndLine();
                $suppressions[] = new Suppression($comment->getStartLine(), $lastLine, $ruleIds);
            }
        }

        return array_values(array_unique($suppressions, SORT_REGULAR));
    }

    private static function followsCode(string $code, int $commentPosition): bool
    {
        $lineStart = strrpos(substr($code, 0, $commentPosition), "\n");
        $before = substr($code, $lineStart === false ? 0 : $lineStart + 1, $commentPosition - ($lineStart === false ? 0 : $lineStart + 1));

        return trim($before) !== '';
    }

    /**
     * @return list<string>|null null when the comment is not a directive
     */
    private static function ruleIds(string $comment): ?array
    {
        if (!preg_match('/' . self::MARKER . '\b(.*)$/m', $comment, $match)) {
            return null;
        }

        $rules = trim(explode('--', $match[1], 2)[0], " \t:*/");
        $ruleIds = preg_split('/[\s,]+/', $rules, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $ruleIds === [] ? [Suppression::ALL_RULES] : $ruleIds;
    }
}
