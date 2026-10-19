<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Git;

/**
 * Runs a command without a shell: arguments are never interpreted.
 */
final readonly class Process
{
    /**
     * @param list<string> $command
     * @return array{int, string, string} exit code, stdout, stderr
     */
    public function run(array $command, string $workingDirectory): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory);
        if (!is_resource($process)) {
            return [127, '', 'cannot start ' . $command[0]];
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
