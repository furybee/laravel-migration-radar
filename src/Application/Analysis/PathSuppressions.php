<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

use Furybee\MigrationRadar\Domain\Migration\Suppression;

/**
 * Rules ignored for whole files, from the configuration: typically old migrations that already ran everywhere.
 */
final readonly class PathSuppressions
{
    /**
     * @param array<string, list<string>> $rulesByPathGlob
     */
    public function __construct(private array $rulesByPathGlob = [])
    {
    }

    public function covers(string $path, string $ruleId): bool
    {
        $normalizedPath = PathNormalizer::normalize($path);
        foreach ($this->rulesByPathGlob as $glob => $ruleIds) {
            $isRuleCovered = in_array(Suppression::ALL_RULES, $ruleIds, true) || in_array($ruleId, $ruleIds, true);
            if ($isRuleCovered && fnmatch(PathNormalizer::normalize($glob), $normalizedPath)) {
                return true;
            }
        }

        return false;
    }
}
