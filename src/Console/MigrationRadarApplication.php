<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Console;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application;

final class MigrationRadarApplication extends Application
{
    public const PACKAGE = 'furybee/laravel-migration-radar';

    public function __construct(string $projectDirectory)
    {
        parent::__construct('migration-radar', self::version());

        $this->addCommands([new AnalyseCommand(new ServiceFactory($projectDirectory)), new RulesCommand()]);
        $this->setDefaultCommand('analyse');
    }

    /**
     * The version Composer installed (the git tag), so a release never needs a version bump in the code.
     */
    private static function version(): string
    {
        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled(self::PACKAGE)) {
            return 'unknown';
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'unknown';
    }
}
