<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\Arguments;
use MiniPaviService\Cli\UsageException;
use PHPUnit\Framework\TestCase;

final class ArgumentsTest extends TestCase
{
    public function testReadsFlagsOptionsAndTheOtherArguments(): void
    {
        $arguments = Arguments::parse(['a.vdt', '--preview', '-2', '--messages', 'm.xml', '-'], ['preview'], ['messages']);

        self::assertTrue($arguments->flag('preview'));
        self::assertSame('m.xml', $arguments->value('messages', ''));
        // "-" and negative numbers are arguments
        self::assertSame(['a.vdt', '-2', '-'], $arguments->positional);
    }

    public function testGivesTheDefaultOfAnOptionNotGiven(): void
    {
        $arguments = Arguments::parse(['a'], ['preview'], ['messages']);

        self::assertFalse($arguments->flag('preview'));
        self::assertSame('none', $arguments->value('messages', 'none'));
    }

    public function testRefusesAnUnknownOption(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('unknown option --verbose');

        Arguments::parse(['--verbose'], ['preview']);
    }

    public function testRefusesAnOptionWithoutItsValue(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('--messages needs a value');

        Arguments::parse(['--messages'], [], ['messages']);
    }
}
