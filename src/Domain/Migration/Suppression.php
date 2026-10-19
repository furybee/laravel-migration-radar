<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration;

/**
 * An ignore directive written in the migration, covering a range of lines.
 */
final readonly class Suppression
{
    public const ALL_RULES = '*';

    /**
     * @param list<string> $ruleIds
     */
    public function __construct(
        public int $fromLine,
        public int $toLine,
        public array $ruleIds,
    ) {
    }

    public function covers(string $ruleId, int $line): bool
    {
        $isRuleCovered = in_array(self::ALL_RULES, $this->ruleIds, true) || in_array($ruleId, $this->ruleIds, true);

        return $isRuleCovered && $line >= $this->fromLine && $line <= $this->toLine;
    }
}
