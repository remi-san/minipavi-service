<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\VdtOffsetCommand;
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
    private const FIXTURES = __DIR__ . '/../fixtures/vdt';

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->tester = new CommandTester(new VdtOffsetCommand());
    }

    public function testWritesTheMovedPage(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute([
            'input' => self::FIXTURES . '/background.vdt',
            'x' => '8',
            'y' => '-2',
        ]));
        self::assertSame(
            (string) file_get_contents(self::FIXTURES . '/background.offset_8_-2.vdt'),
            $this->tester->getDisplay(),
        );
    }

    public function testWritesTheMovedPageToTheOutputFile(): void
    {
        $output = tempnam(sys_get_temp_dir(), prefix: 'vdt');
        self::assertIsString($output);
        try {
            self::assertSame(Command::SUCCESS, $this->tester->execute([
                'input' => self::FIXTURES . '/background.vdt',
                'x' => '8',
                'y' => '-2',
                'output' => $output,
            ]));
            self::assertFileEquals(self::FIXTURES . '/background.offset_8_-2.vdt', $output);
            self::assertSame('', $this->tester->getDisplay());
        } finally {
            unlink($output);
        }
    }

    public function testPreviewsTheScreenBeforeAndAfter(): void
    {
        $vdt = new Vdt((string) file_get_contents(self::FIXTURES . '/background.vdt'));

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            'input' => self::FIXTURES . '/background.vdt',
            'x' => '8',
            'y' => '-2',
            '--preview' => true,
        ]));
        self::assertSame(
            "Before:\n" . Screen::of($vdt)->preview() . "After (8, -2):\n"
                . Screen::of((new Offset(8, -2))->apply($vdt))->preview(),
            $this->tester->getDisplay(),
        );
    }

    public function testFailsOnAFileItCannotRead(): void
    {
        self::assertSame(Command::FAILURE, $this->tester->execute([
            'input' => self::FIXTURES . '/missing.vdt',
            'x' => '1',
            'y' => '1',
        ], ['capture_stderr_separately' => true]));
        self::assertSame('cannot read ' . self::FIXTURES . "/missing.vdt\n", $this->tester->getErrorOutput());
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
