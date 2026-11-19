<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The layers of src/ depend one way: Minitel (the terminal's facts) <- Vdt (Videotex, and the
 * screen it draws) <- Page (presentation)
 */
final class LayersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function layers(): iterable
    {
        yield 'Minitel' => ['Minitel', ['Vdt', 'Page']];
        yield 'Vdt' => ['Vdt', ['Page']];
    }

    /**
     * @param list<string> $above namespaces it must not use
     */
    #[DataProvider('layers')]
    public function testDoesntUseTheLayersAboveIt(string $layer, array $above): void
    {
        $src = \dirname(__DIR__) . '/src/';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src . $layer, flags: \FilesystemIterator::SKIP_DOTS),
        );
        $pattern = '/MiniPaviService\\\\(?:' . implode('|', $above) . ')\\\\\w+/';
        $uses = [];
        foreach ($files as $file) {
            // A file's info is its path as text
            $path = (string) $file;
            preg_match_all($pattern, (string) file_get_contents($path), $matches);
            foreach (array_unique($matches[0]) as $class) {
                $uses[] = substr($path, \strlen($src)) . ': ' . $class;
            }
        }
        sort($uses);

        self::assertSame([], $uses);
    }
}
