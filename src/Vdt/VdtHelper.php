<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPavi\MiniPaviCli;

/**
 * Videotex helpers of MiniPaviCli, as static methods giving Vdt
 */
final class VdtHelper
{
    public static function setPos(int $col, int $row): Vdt
    {
        return new Vdt(MiniPaviCli::setPos($col, $row));
    }

    public static function writeLine0(string $text, bool $blink = false): Vdt
    {
        return new Vdt(MiniPaviCli::writeLine0($text, $blink));
    }

    public static function clearScreen(): Vdt
    {
        return new Vdt(MiniPaviCli::clearScreen());
    }

    public static function repeatChar(string $char, int $count): Vdt
    {
        return new Vdt(MiniPaviCli::repeatChar($char, $count));
    }

    public static function writeCentered(int $row, string $text, string $attr = ''): Vdt
    {
        return new Vdt(MiniPaviCli::writeCentered($row, $text, $attr));
    }

    public static function toG2(string $text): Vdt
    {
        return new Vdt(MiniPaviCli::toG2($text));
    }
}
