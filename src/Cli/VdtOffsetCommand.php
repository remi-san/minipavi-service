<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Vdt\Vdt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Moves the content of a Videotex (.vdt) page by x columns and y rows (Vdt::offset()): x
 * moves right (negative: left), y moves down (negative: up). The result goes to the output
 * file, or to the standard output; input "-" reads the standard input. With --preview, the
 * screen is printed as text instead, before and after.
 */
#[AsCommand(name: 'vdt-offset', description: 'Moves a Videotex page x columns right and y rows down (negative: left, up)')]
final class VdtOffsetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'The Videotex file (- reads the standard input)')
            ->addArgument('x', InputArgument::REQUIRED, 'Columns to move right (negative: left)')
            ->addArgument('y', InputArgument::REQUIRED, 'Rows to move down (negative: up)')
            ->addArgument('output', InputArgument::OPTIONAL, 'The file to write (default: the standard output)')
            ->addOption('preview', null, InputOption::VALUE_NONE, 'Prints the screen as text, before and after')
            ->setHelp('Negative numbers are read as options: put <info>--</info> before the arguments, e.g. <info>vdt-offset -- background.vdt 8 -2</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = Input::string($input, 'input');
        [$x, $y] = [self::int($input, 'x'), self::int($input, 'y')];
        $errors = (new SymfonyStyle($input, $output))->getErrorStyle();

        try {
            $vdt = Vdt::fromFile($file === '-' ? 'php://stdin' : $file);
        } catch (\InvalidArgumentException) {
            $errors->writeln(Input::escape("cannot read $file"));

            return self::FAILURE;
        }
        $moved = $vdt->offset($x, $y);

        if ($input->getOption('preview') === true) {
            $output->write("Before:\n" . $vdt->screen()->preview() . "After ($x, $y):\n" . $moved->screen()->preview(), false, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }
        $target = $input->getArgument('output');
        if (!\is_string($target)) {
            $output->write($moved->toString(), false, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }
        if (@file_put_contents($target, $moved->toString()) === false) {
            $errors->writeln(Input::escape("cannot write $target"));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @throws InvalidArgumentException if it isn't an integer
     */
    private static function int(InputInterface $input, string $name): int
    {
        $value = Input::string($input, $name);
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new InvalidArgumentException("$name must be an integer, not $value");
        }

        return (int) $value;
    }
}
