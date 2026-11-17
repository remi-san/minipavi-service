<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Io;

use MiniPaviService\Io\Files;
use PHPUnit\Framework\TestCase;

/**
 * Without PHP's warning on a failure: the tests fail on warnings (phpunit.xml.dist)
 */
final class FilesTest extends TestCase
{
    public function testReadsAndWritesAFile(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'files');
        try {
            self::assertTrue(Files::write($file, "\x0C\x1F\x41\x41OK"));
            self::assertSame("\x0C\x1F\x41\x41OK", Files::read($file));
        } finally {
            unlink($file);
        }
    }

    public function testReadsNothingFromAMissingFile(): void
    {
        self::assertNull(Files::read(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)) . '.vdt'));
    }

    public function testWritesNothingInAMissingFolder(): void
    {
        self::assertFalse(Files::write(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)) . '/out.vdt', 'x'));
    }

    public function testKeepsTheErrorHandlerOfTheCaller(): void
    {
        $handler = static fn(): bool => false;
        set_error_handler($handler);
        try {
            Files::read(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)));
            self::assertSame($handler, set_error_handler(null));
        } finally {
            restore_error_handler();
            restore_error_handler();
        }
    }
}
