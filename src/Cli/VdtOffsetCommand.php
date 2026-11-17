<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Vdt\Vdt;

/**
 * Moves the content of a Videotex (.vdt) page by x columns and y rows (Vdt::offset()): x
 * moves right (negative: left), y moves down (negative: up). The result goes to the output
 * file, or to the console; input "-" reads the standard input. With --preview, the screen is
 * printed as text instead, before and after.
 */
final readonly class VdtOffsetCommand implements Command
{
    public function name(): string
    {
        return 'vdt-offset';
    }

    public function usage(): string
    {
        return '[--preview] <input.vdt> <x> <y> [<output.vdt>]';
    }

    public function description(): string
    {
        return 'Moves a Videotex page x columns right and y rows down (negative: left, up); - reads the standard input';
    }

    public function run(array $arguments, Console $console): ExitCode
    {
        $arguments = Arguments::parse($arguments, flags: ['preview']);
        $positional = $arguments->positional;
        if (\count($positional) < 3 || \count($positional) > 4) {
            throw new UsageException('expects an input, x and y, and optionally an output');
        }
        [$input, $x, $y] = [$positional[0], self::int($positional[1], 'x'), self::int($positional[2], 'y')];

        try {
            $vdt = Vdt::fromFile($input === '-' ? 'php://stdin' : $input);
        } catch (\InvalidArgumentException) {
            $console->error("cannot read $input\n");

            return ExitCode::Failure;
        }
        $moved = $vdt->offset($x, $y);

        if ($arguments->flag('preview')) {
            $console->write("Before:\n" . $vdt->screen()->preview() . "After ($x, $y):\n" . $moved->screen()->preview());

            return ExitCode::Success;
        }
        $output = $positional[3] ?? null;
        if ($output === null) {
            $console->write($moved->toString());

            return ExitCode::Success;
        }
        if (@file_put_contents($output, $moved->toString()) === false) {
            $console->error("cannot write $output\n");

            return ExitCode::Failure;
        }

        return ExitCode::Success;
    }

    /**
     * @throws UsageException if it isn't an integer
     */
    private static function int(string $value, string $name): int
    {
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new UsageException("$name must be an integer, not $value");
        }

        return (int) $value;
    }
}
