<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\VdtOffsetCommand;
use MiniPaviService\Vdt\Vdt;
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
        $vdt = Vdt::fromFile(self::FIXTURES . '/background.vdt');

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            'input' => self::FIXTURES . '/background.vdt',
            'x' => '8',
            'y' => '-2',
            '--preview' => true,
        ]));
        self::assertSame(
            "Before:\n" . $vdt->screen()->preview() . "After (8, -2):\n" . $vdt->offset(8, -2)->screen()->preview(),
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

    public function testRefusesAnOffsetThatIsNotAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('x must be an integer, not one');

        $this->tester->execute(['input' => 'a.vdt', 'x' => 'one', 'y' => '1']);
    }
}
