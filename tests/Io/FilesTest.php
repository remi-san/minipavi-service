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
        $file = (string) tempnam(sys_get_temp_dir(), prefix: 'files');
        try {
            self::assertTrue((new Files())->write($file, content: "\x0C\x1F\x41\x41OK"));
            self::assertSame("\x0C\x1F\x41\x41OK", (new Files())->read($file));
        } finally {
            unlink($file);
        }
    }

    public function testReadsNothingFromAMissingFile(): void
    {
        self::assertNull((new Files())->read(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)) . '.vdt'));
    }

    public function testWritesNothingInAMissingFolder(): void
    {
        self::assertFalse((new Files())->write(
            sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)) . '/out.vdt',
            content: 'x',
        ));
    }

    public function testKeepsTheErrorHandlerOfTheCaller(): void
    {
        $handler = static fn(): bool => false;
        set_error_handler($handler);
        try {
            (new Files())->read(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)));
            self::assertSame($handler, set_error_handler(null));
        } finally {
            restore_error_handler();
            restore_error_handler();
        }
    }

    public function testFindsTheFilesWithTheExtensionOfAFolderAndItsSubfoldersInOrder(): void
    {
        $dir = self::folder();
        try {
            // Written out of order: a folder lists its files in no particular order
            foreach (['sub/d.xml', 'b.xml', 'sub/MENU.XML', 'a.xml', 'notes.txt', 'c.xmlx'] as $file) {
                file_put_contents("{$dir}/{$file}", data: 'x');
            }

            self::assertSame(
                ["{$dir}/a.xml", "{$dir}/b.xml", "{$dir}/sub/MENU.XML", "{$dir}/sub/d.xml"],
                (new Files())->find($dir, extension: 'xml'),
            );
        } finally {
            self::remove($dir);
        }
    }

    public function testFindsAFileWhateverItsExtension(): void
    {
        $dir = self::folder();
        try {
            file_put_contents("{$dir}/notes.txt", data: 'x');

            self::assertSame(["{$dir}/notes.txt"], (new Files())->find("{$dir}/notes.txt", extension: 'xml'));
        } finally {
            self::remove($dir);
        }
    }

    public function testSkipsALinkToAFolder(): void
    {
        $dir = self::folder();
        try {
            file_put_contents("{$dir}/sub/home.xml", data: 'x');
            symlink("{$dir}/sub", "{$dir}/archive.xml");

            self::assertSame(["{$dir}/sub/home.xml"], (new Files())->find($dir, extension: 'xml'));
        } finally {
            self::remove($dir);
        }
    }

    public function testFindsNothingAtAMissingPath(): void
    {
        self::assertNull((new Files())->find(
            sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)),
            extension: 'xml',
        ));
    }

    /**
     * A new folder, with a subfolder "sub"
     */
    private static function folder(): string
    {
        $dir = sys_get_temp_dir() . '/files-' . bin2hex(random_bytes(4));
        mkdir("{$dir}/sub", permissions: 0o777, recursive: true);

        return $dir;
    }

    /**
     * Removes the folder and what it holds, links without what they point to
     */
    private static function remove(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
