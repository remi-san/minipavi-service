<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Vdt\Vdt;

/**
 * The .vdt files of the fixtures
 */
final class VdtFixtures
{
    public const FOLDER = __DIR__ . '/../fixtures/vdt';

    /**
     * Content of a .vdt file of the fixtures
     */
    public static function content(string $name): Vdt
    {
        return new Vdt((string) file_get_contents(self::FOLDER . "/{$name}.vdt"));
    }
}
