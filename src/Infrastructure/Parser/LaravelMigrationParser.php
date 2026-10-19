<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use Furybee\MigrationRadar\Application\Port\MigrationNotParsable;
use Furybee\MigrationRadar\Application\Port\MigrationParser;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Infrastructure\ProjectPath;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Reads what a Laravel migration does without running it.
 */
final readonly class LaravelMigrationParser implements MigrationParser
{
    private Parser $parser;

    /**
     * @param string $projectDirectory relative migration paths are read from there
     */
    public function __construct(private string $projectDirectory)
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function parse(string $path): Migration
    {
        $code = @file_get_contents(ProjectPath::absolute($this->projectDirectory, $path));
        if ($code === false) {
            throw MigrationNotParsable::at($path, 'file cannot be read');
        }

        try {
            $ast = $this->parser->parse($code) ?? [];
        } catch (Error $e) {
            throw MigrationNotParsable::at($path, $e->getMessage());
        }
        $ast = (new NodeTraverser(new NameResolver()))->traverse($ast);

        $class = (new NodeFinder())->findFirstInstanceOf($ast, Node\Stmt\Class_::class);
        if ($class === null) {
            throw MigrationNotParsable::at($path, 'no migration class found');
        }

        $up = $class->getMethod('up');
        if ($up === null) {
            return new Migration($path, []);
        }

        $strings = new StringResolver(self::stringConstants($class));
        $interpreter = new UpMethodInterpreter($strings, new BlueprintInterpreter($strings));

        return new Migration(
            $path,
            $interpreter->interpret($up->stmts ?? []),
            (new SuppressionCollector())->collect($ast),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function stringConstants(Node\Stmt\Class_ $class): array
    {
        $constants = [];
        foreach ($class->getConstants() as $declaration) {
            foreach ($declaration->consts as $constant) {
                if ($constant->value instanceof Scalar\String_) {
                    $constants[$constant->name->toString()] = $constant->value->value;
                }
            }
        }

        return $constants;
    }
}
