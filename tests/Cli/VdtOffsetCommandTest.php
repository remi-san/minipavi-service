<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\ExitCode;
use MiniPaviService\Cli\UsageException;
use MiniPaviService\Cli\VdtOffsetCommand;
use MiniPaviService\Tests\Support\MemoryConsole;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VdtOffsetCommandTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/vdt';

    private MemoryConsole $console;

    protected function setUp(): void
    {
        $this->console = new MemoryConsole();
    }

    public function testWritesTheMovedPage(): void
    {
        self::assertSame(ExitCode::Success, (new VdtOffsetCommand())->run([self::FIXTURES . '/background.vdt', '8', '-2'], $this->console->console));
        self::assertSame((string) file_get_contents(self::FIXTURES . '/background.offset_8_-2.vdt'), $this->console->output());
    }

    public function testWritesTheMovedPageToTheOutputFile(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'vdt');
        self::assertIsString($output);
        try {
            self::assertSame(ExitCode::Success, (new VdtOffsetCommand())->run([self::FIXTURES . '/background.vdt', '8', '-2', $output], $this->console->console));
            self::assertFileEquals(self::FIXTURES . '/background.offset_8_-2.vdt', $output);
            self::assertSame('', $this->console->output());
        } finally {
            unlink($output);
        }
    }

    public function testPreviewsTheScreenBeforeAndAfter(): void
    {
        $vdt = Vdt::fromFile(self::FIXTURES . '/background.vdt');

        self::assertSame(ExitCode::Success, (new VdtOffsetCommand())->run(['--preview', self::FIXTURES . '/background.vdt', '8', '-2'], $this->console->console));
        self::assertSame("Before:\n" . $vdt->screen()->preview() . "After (8, -2):\n" . $vdt->offset(8, -2)->screen()->preview(), $this->console->output());
    }

    public function testFailsOnAFileItCannotRead(): void
    {
        self::assertSame(ExitCode::Failure, (new VdtOffsetCommand())->run([self::FIXTURES . '/missing.vdt', '1', '1'], $this->console->console));
        self::assertSame('cannot read ' . self::FIXTURES . "/missing.vdt\n", $this->console->errors());
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function wrongCommandLines(): iterable
    {
        yield 'no y' => [['a.vdt', '1']];
        yield 'too many' => [['a.vdt', '1', '1', 'b.vdt', 'c.vdt']];
        yield 'x not an integer' => [['a.vdt', 'one', '1']];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('wrongCommandLines')]
    public function testRefusesAWrongCommandLine(array $arguments): void
    {
        $this->expectException(UsageException::class);

        (new VdtOffsetCommand())->run($arguments, $this->console->console);
    }
}
