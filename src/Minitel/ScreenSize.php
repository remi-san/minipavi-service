<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

/**
 * Size of the Minitel screen, for the whole framework: rows 1 to ROWS below the status line
 * (row 0), of COLS columns
 */
final readonly class ScreenSize
{
    public const ROWS = 24;
    public const COLS = 40;

    private function __construct() {}
}
