<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\VdtOffsetCommand;
use MiniPaviService\Io\FileWriter;
use MiniPaviService\Tests\Support\MemoryFiles;
use MiniPaviService\Tests\Support\VdtFixtures;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class VdtOffsetCommandTest extends TestCase
{
    private MemoryFiles $files;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->files = new MemoryFiles([
            'background.vdt' => (string) file_get_contents(VdtFixtures::FOLDER . '/background.vdt'),
        ]);
        $this->tester = new CommandTester(new VdtOffsetCommand($this->files, $this->files));
    }

    public function testWritesTheMovedPage(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute([
            'input' => 'background.vdt',
            'x' => '8',
            'y' => '-2',
        ]));
        self::assertSame(
            (string) file_get_contents(VdtFixtures::FOLDER . '/background.offset_8_-2.vdt'),
            $this->tester->getDisplay(),
        );
        self::assertSame([], $this->files->written);
    }

    public function testWritesTheMovedPageToTheOutputFile(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute([
            'input' => 'background.vdt',
            'x' => '8',
            'y' => '-2',
            'output' => 'moved.vdt',
        ]));
        self::assertSame(
            ['moved.vdt' => (string) file_get_contents(VdtFixtures::FOLDER . '/background.offset_8_-2.vdt')],
            $this->files->written,
        );
        self::assertSame('', $this->tester->getDisplay());
    }

    public function testReadsTheStandardInputForADash(): void
    {
        $files = new MemoryFiles(['php://stdin' => "\x1F\x41\x41AB"]);
        $tester = new CommandTester(new VdtOffsetCommand($files, $files));

        self::assertSame(Command::SUCCESS, $tester->execute(['input' => '-', 'x' => '2', 'y' => '1']));
        self::assertSame(['php://stdin'], $files->read);
        self::assertSame(
            (new Offset(2, 1))
                ->apply(new Vdt("\x1F\x41\x41AB"))
                ->toString(),
            $tester->getDisplay(),
        );
    }

    public function testPreviewsTheScreenBeforeAndAfter(): void
    {
        $vdt = VdtFixtures::content('background');

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            'input' => 'background.vdt',
            'x' => '8',
            'y' => '-2',
            '--preview' => true,
        ]));
        self::assertSame(
            "Before:\n" . Screen::of($vdt)->preview() . "After (8, -2):\n"
                . Screen::of((new Offset(8, -2))->apply($vdt))->preview(),
            $this->tester->getDisplay(),
        );
        self::assertSame([], $this->files->written);
    }

    public function testFailsOnAFileItCannotRead(): void
    {
        self::assertSame(Command::FAILURE, $this->tester->execute([
            'input' => 'missing.vdt',
            'x' => '1',
            'y' => '1',
        ], ['capture_stderr_separately' => true]));
        self::assertSame("cannot read missing.vdt\n", $this->tester->getErrorOutput());
        self::assertSame('', $this->tester->getDisplay());
    }

    public function testFailsOnAnOutputFileItCannotWrite(): void
    {
        $tester = new CommandTester(new VdtOffsetCommand($this->files, new class implements FileWriter {
            public function write(string $file, string $content): bool
            {
                return false;
            }
        }));

        self::assertSame(Command::FAILURE, $tester->execute([
            'input' => 'background.vdt',
            'x' => '1',
            'y' => '1',
            'output' => 'moved.vdt',
        ], ['capture_stderr_separately' => true]));
        self::assertSame("cannot write moved.vdt\n", $tester->getErrorOutput());
        self::assertSame('', $tester->getDisplay());
    }

    public function testNeedsXAndY(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tester->execute(['input' => 'a.vdt', 'x' => '1']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIntegers(): iterable
    {
        yield 'a word' => ['one'];
        yield 'something after it' => ['12abc'];
        yield 'something before it' => ['abc12'];
        yield 'two numbers' => ['1 2'];
        yield 'spaces around it' => [' 12 '];
        yield 'a final newline' => ["12\n"];
        yield 'a sign alone' => ['-'];
        yield 'a decimal' => ['1.5'];
    }

    #[DataProvider('notIntegers')]
    public function testRefusesAnOffsetThatIsNotAnInteger(string $offset): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("x must be an integer, not {$offset}");

        $this->tester->execute(['input' => 'a.vdt', 'x' => $offset, 'y' => '1']);
    }

    public function testRefusesAYOffsetThatIsNotAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('y must be an integer, not 2abc');

        $this->tester->execute(['input' => 'a.vdt', 'x' => '1', 'y' => '2abc']);
    }
}
