<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use Furybee\MigrationRadar\Domain\Migration\Suppression;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Reads `// migration-radar-ignore rule-a, rule-b -- reason` comments.
 * A comment covers the statement it precedes, closures included; without rule ids it covers every rule.
 */
final class SuppressionCollector
{
    public const MARKER = 'migration-radar-ignore';

    /**
     * @param array<Node> $ast
     * @return list<Suppression>
     */
    public function collect(array $ast): array
    {
        $suppressions = [];
        foreach ((new NodeFinder())->findInstanceOf($ast, Node\Stmt::class) as $statement) {
            foreach ($statement->getComments() as $comment) {
                $ruleIds = self::ruleIds($comment->getText());
                if ($ruleIds !== null) {
                    $suppressions[] = new Suppression($comment->getStartLine(), $statement->getEndLine(), $ruleIds);
                }
            }
        }

        return $suppressions;
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
