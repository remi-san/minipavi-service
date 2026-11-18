<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Minitel;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    public function testGoesToTheEndOfTheRowByDefault(): void
    {
        self::assertSame(31, (new Span(new Position(5, col: 10)))->length);
        self::assertSame(40, (new Span(new Position(0)))->length);
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function offTheScreen(): iterable
    {
        yield 'row before the status line' => [static fn() => new Position(-1), 'Row -1 is off the screen (0 to 24)'];
        yield 'row after the last' => [static fn() => new Position(25), 'Row 25 is off the screen (0 to 24)'];
        yield 'column 0' => [static fn() => new Position(1, col: 0), 'Column 0 is off the screen (1 to 40)'];
        yield 'column after the last' => [
            static fn() => new Position(1, col: 41),
            'Column 41 is off the screen (1 to 40)',
        ];
        yield 'past the last column' => [
            static fn() => new Span(new Position(1, col: 35), length: 7),
            'Length 7 from column 35 goes past column 40',
        ];
        yield 'empty' => [
            static fn() => new Span(new Position(1), length: 0),
            'Length 0 from column 1 goes past column 40',
        ];
    }

    /**
     * @param \Closure(): mixed $build
     */
    #[DataProvider('offTheScreen')]
    public function testCannotBeOffTheScreen(\Closure $build, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $build();
    }
}
