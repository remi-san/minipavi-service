<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Io\FileReader;
use MiniPaviService\Io\Files;
use MiniPaviService\Io\FileWriter;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Screen;
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
#[AsCommand(
    name: 'vdt-offset',
    description: 'Moves a Videotex page x columns right and y rows down (negative: left, up)',
)]
final class VdtOffsetCommand extends Command
{
    /**
     * @param FileReader $reader reads the input file (php://stdin for "-")
     * @param FileWriter $writer writes the output file
     */
    public function __construct(
        private readonly FileReader $reader = new Files(),
        private readonly FileWriter $writer = new Files(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'input',
                InputArgument::REQUIRED,
                description: 'The Videotex file (- reads the standard input)',
            )
            ->addArgument('x', InputArgument::REQUIRED, description: 'Columns to move right (negative: left)')
            ->addArgument('y', InputArgument::REQUIRED, description: 'Rows to move down (negative: up)')
            ->addArgument(
                'output',
                InputArgument::OPTIONAL,
                description: 'The file to write (default: the standard output)',
            )
            ->addOption(
                'preview',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Prints the screen as text, before and after',
            )
            ->setHelp(
                'Negative numbers are read as options: put <info>--</info> before the arguments, e.g. <info>vdt-offset -- background.vdt 8 -2</info>',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = Input::string($input, argument: 'input');
        [$x, $y] = [self::int($input, name: 'x'), self::int($input, name: 'y')];
        $errors = (new SymfonyStyle($input, $output))->getErrorStyle();

        $bytes = $this->reader->read($file === '-' ? 'php://stdin' : $file);
        if ($bytes === null) {
            $errors->writeln(Input::escape("cannot read {$file}"));

            return self::FAILURE;
        }
        $vdt = new Vdt($bytes);
        $moved = (new Offset($x, $y))->apply($vdt);

        if ($input->getOption('preview') === true) {
            $output->write(
                "Before:\n" . Screen::of($vdt)->preview() . "After ({$x}, {$y}):\n" . Screen::of($moved)->preview(),
                newline: false,
                options: OutputInterface::OUTPUT_RAW,
            );

            return self::SUCCESS;
        }
        $target = $input->getArgument('output');
        if (!\is_string($target)) {
            $output->write($moved->toString(), newline: false, options: OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }
        if (!$this->writer->write($target, $moved->toString())) {
            $errors->writeln(Input::escape("cannot write {$target}"));

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
        // \z, as $ allows a final newline
        if (!preg_match('/^-?\d+\z/', $value)) {
            throw new InvalidArgumentException("{$name} must be an integer, not {$value}");
        }

        return (int) $value;
    }
}
