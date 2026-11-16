<?php

namespace MiniPaviService\Vdt;

use MiniPavi\MiniPaviCli;

/**
 * Videotex helpers of MiniPaviCli, as static methods
 */
final class VdtHelper {

    public static function setPos(int $col, int $row): string {
        return MiniPaviCli::setPos($col, $row);
    }

    public static function writeLine0(string $text, bool $blink = false): string {
        return MiniPaviCli::writeLine0($text, $blink);
    }

    public static function clearScreen(): string {
        return MiniPaviCli::clearScreen();
    }

    public static function repeatChar(string $char, int $count): string {
        return MiniPaviCli::repeatChar($char, $count);
    }

    public static function writeCentered(int $row, string $text, string $attr = ''): string {
        return MiniPaviCli::writeCentered($row, $text, $attr);
    }

    public static function toG2(string $text): string {
        return MiniPaviCli::toG2($text);
    }
}
