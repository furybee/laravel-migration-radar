<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Rules;

final readonly class RuleSet
{
    /**
     * @param list<Rule> $rules
     */
    public function __construct(public array $rules)
    {
    }

    public static function default(): self
    {
        return new self([
            new Rules\NotNullColumnWithoutDefaultRule(),
            new Rules\AddColumnRewritesTableRule(),
            new Rules\ChangeColumnRule(),
            new Rules\RenameColumnRule(),
            new Rules\RenameTableRule(),
            new Rules\DropColumnRule(),
            new Rules\DropTableRule(),
            new Rules\AddIndexLocksTableRule(),
            new Rules\AddForeignKeyLocksTableRule(),
            new Rules\MixedSchemaAndDataRule(),
            new Rules\RawSqlRule(),
        ]);
    }

    /**
     * @param list<string> $ruleIds
     * @throws InvalidConfiguration when an id matches no rule, a typo would silently keep the rule on
     */
    public function without(array $ruleIds): self
    {
        $unknown = array_diff($ruleIds, $this->ids());
        if ($unknown !== []) {
            throw new InvalidConfiguration('Unknown rule(s): ' . implode(', ', $unknown) . '. Available: ' . implode(', ', $this->ids()));
        }

        return new self(array_values(array_filter(
            $this->rules,
            static fn(Rule $rule) => !in_array($rule->id(), $ruleIds, true),
        )));
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn(Rule $rule) => $rule->id(), $this->rules);
    }
}
