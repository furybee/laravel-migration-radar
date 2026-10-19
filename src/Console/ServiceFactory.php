<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Console;

use Furybee\MigrationRadar\Application\Analysis\AnalyseMigrations;
use Furybee\MigrationRadar\Application\Analysis\BuildDatabaseContext;
use Furybee\MigrationRadar\Application\Analysis\PathSuppressions;
use Furybee\MigrationRadar\Application\Analysis\RuleSet;
use Furybee\MigrationRadar\Application\Analysis\SelectMigrations;
use Furybee\MigrationRadar\Application\Configuration\Configuration;
use Furybee\MigrationRadar\Application\Configuration\InvalidConfiguration;
use Furybee\MigrationRadar\Application\Analysis\ReportRenderer;
use Furybee\MigrationRadar\Infrastructure\Configuration\PhpConfigurationLoader;
use Furybee\MigrationRadar\Infrastructure\Database\DatabaseUrl;
use Furybee\MigrationRadar\Infrastructure\Database\NoDatabaseInspector;
use Furybee\MigrationRadar\Infrastructure\Database\PdoDatabaseInspector;
use Furybee\MigrationRadar\Infrastructure\FilesystemMigrationFinder;
use Furybee\MigrationRadar\Infrastructure\Git\GitChangedFiles;
use Furybee\MigrationRadar\Infrastructure\Parser\LaravelMigrationParser;
use Furybee\MigrationRadar\Infrastructure\ProjectPath;
use Furybee\MigrationRadar\Infrastructure\Report\GithubReportRenderer;
use Furybee\MigrationRadar\Infrastructure\Report\GitlabReportRenderer;
use Furybee\MigrationRadar\Infrastructure\Report\JsonReportRenderer;
use Furybee\MigrationRadar\Infrastructure\Report\TextReportRenderer;

/**
 * Composition root: the only place that knows the concrete adapters.
 */
final readonly class ServiceFactory
{
    public const FORMATS = ['text', 'json', 'github', 'gitlab'];

    public function __construct(private string $projectDirectory)
    {
    }

    /**
     * The given file, else `migration-radar.php` in the project when present, else the defaults.
     */
    public function configuration(?string $file): Configuration
    {
        $loader = new PhpConfigurationLoader();
        if ($file !== null) {
            return $loader->load(ProjectPath::absolute($this->projectDirectory, $file));
        }

        $defaultFile = ProjectPath::absolute($this->projectDirectory, PhpConfigurationLoader::DEFAULT_FILE);

        return is_file($defaultFile) ? $loader->load($defaultFile) : new Configuration();
    }

    public function selectMigrations(): SelectMigrations
    {
        return new SelectMigrations(
            new FilesystemMigrationFinder($this->projectDirectory),
            new GitChangedFiles($this->projectDirectory),
        );
    }

    public function buildDatabaseContext(Configuration $configuration): BuildDatabaseContext
    {
        $inspector = $configuration->dsn === null
            ? new NoDatabaseInspector()
            : PdoDatabaseInspector::fromUrl(DatabaseUrl::parse($configuration->dsn));

        return new BuildDatabaseContext($inspector);
    }

    public function analyseMigrations(Configuration $configuration): AnalyseMigrations
    {
        return new AnalyseMigrations(
            new LaravelMigrationParser($this->projectDirectory),
            RuleSet::default()->without($configuration->disabledRules),
            new PathSuppressions($configuration->ignoredPaths),
        );
    }

    public function renderer(string $format): ReportRenderer
    {
        return match ($format) {
            'text' => new TextReportRenderer(),
            'json' => new JsonReportRenderer(),
            'github' => new GithubReportRenderer(),
            'gitlab' => new GitlabReportRenderer(),
            default => throw new InvalidConfiguration("Unknown format \"{$format}\", expected " . implode(', ', self::FORMATS)),
        };
    }
}
