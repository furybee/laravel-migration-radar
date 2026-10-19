<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Console;

use Furybee\MigrationRadar\Application\Configuration\Configuration;
use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;
use Furybee\MigrationRadar\Application\Port\ChangedFilesUnavailable;
use Furybee\MigrationRadar\Application\Port\DatabaseUnreachable;
use Furybee\MigrationRadar\Domain\Database\InvalidPlatform;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'analyse', description: 'Report the migrations that are dangerous to run in production')]
final class AnalyseCommand extends Command
{
    public const FAIL_ON_NEVER = 'never';
    private const DSN_ENVIRONMENT_VARIABLE = 'MIGRATION_RADAR_DSN';

    public function __construct(private readonly ServiceFactory $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Migration files or directories (default: "paths" from the configuration, else database/migrations)')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Configuration file (default: migration-radar.php when present)')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only migrations added or modified since this git reference, e.g. origin/main')
            ->addOption('dsn', null, InputOption::VALUE_REQUIRED, 'Database URL to read the version and table sizes, e.g. mysql://user:pass@host/db (or ' . self::DSN_ENVIRONMENT_VARIABLE . ')')
            ->addOption('platform', 'p', InputOption::VALUE_REQUIRED, 'Target database, e.g. mysql:8.0.35, mariadb:10.11, pgsql:16')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: ' . implode(', ', ServiceFactory::FORMATS), 'text')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Lowest severity failing the command: error, warning, info or never', Severity::Error->value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            $failOn = $this->failOn(self::stringOption($input, 'fail-on') ?? Severity::Error->value);
            $renderer = $this->services->renderer(self::stringOption($input, 'format') ?? 'text');
            $configuration = $this->configuration($input);

            $selection = $this->services->selectMigrations();
            $context = $this->services->buildDatabaseContext($configuration)->build($configuration);
            $analyse = $this->services->analyseMigrations($configuration);

            $since = self::stringOption($input, 'since');
            $report = $since === null
                ? $analyse->analyse($selection->all($configuration->paths), $context)
                : $analyse->analyseUnreleased($selection->changedSince($configuration->paths, $since), $context);
        } catch (InvalidConfiguration | InvalidPlatform | DatabaseUnreachable | ChangedFilesUnavailable $e) {
            $errorOutput->writeln("<error>{$e->getMessage()}</error>");

            return Command::INVALID;
        }

        $output->write($renderer->render($report), false, OutputInterface::OUTPUT_RAW);

        return $failOn !== null && $report->hasIssuesAtLeast($failOn) ? Command::FAILURE : Command::SUCCESS;
    }

    private function configuration(InputInterface $input): Configuration
    {
        $configuration = $this->services->configuration(self::stringOption($input, 'config'));

        $paths = $input->getArgument('paths');
        $paths = is_array($paths) ? array_values(array_filter($paths, 'is_string')) : [];
        if ($paths !== []) {
            $configuration = $configuration->withPaths($paths);
        }

        $platform = self::stringOption($input, 'platform');
        if ($platform !== null) {
            $configuration = $configuration->withPlatform($platform);
        }

        $dsn = self::stringOption($input, 'dsn') ?? (getenv(self::DSN_ENVIRONMENT_VARIABLE) ?: null);

        return $dsn === null ? $configuration : $configuration->withDsn($dsn);
    }

    /**
     * @return Severity|null null to never fail
     */
    private function failOn(string $value): ?Severity
    {
        if ($value === self::FAIL_ON_NEVER) {
            return null;
        }

        return Severity::tryFrom($value) ?? throw new InvalidConfiguration("Invalid --fail-on \"{$value}\", expected error, warning, info or never");
    }

    private static function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
