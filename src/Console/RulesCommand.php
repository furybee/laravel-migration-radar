<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Console;

use Furybee\MigrationRadar\Application\Analysis\RuleSet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rules', description: 'List the rules and their ids, to use in the configuration and ignore comments')]
final class RulesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (RuleSet::default()->rules as $rule) {
            $output->writeln(sprintf('  <info>%-32s</info> %s', $rule->id(), $rule->description()));
        }

        return Command::SUCCESS;
    }
}
