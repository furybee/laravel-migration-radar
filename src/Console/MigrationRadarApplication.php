<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Console;

use Symfony\Component\Console\Application;

final class MigrationRadarApplication extends Application
{
    public const VERSION = '0.1.0';

    public function __construct(string $projectDirectory)
    {
        parent::__construct('migration-radar', self::VERSION);

        $this->addCommands([new AnalyseCommand(new ServiceFactory($projectDirectory)), new RulesCommand()]);
        $this->setDefaultCommand('analyse');
    }
}
