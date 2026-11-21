<?php

namespace MiniPavi;

/**
 * Types of the MiniPaviCli members the framework uses, which the library doesn't declare
 * (ludosevilla/minipavi-cli 1.1): Mago patches the library's class with them (patches in
 * mago.toml). Its methods build Videotex content
 * and MiniPavi commands from their arguments, so these types always hold. Its static
 * properties, which hold the gateway's request, are left untyped: MiniPaviCliGateway checks them.
 */
class MiniPaviCli
{
    public static $remoteAddr = '';
    public static $content = [];
    public static $fctn = '';
    public static $context = '';
    public static $typeSocket;
    public static $versionMinitel;

    /**
     * Reads the gateway's request into the static properties
     *
     * @param bool $ignoreWMState
     * @return void
     */
    public static function start($ignoreWMState = true) {}

    /**
     * Writes the response to the gateway
     *
     * @param string $content
     * @param string $next
     * @param string $context
     * @param bool $echo
     * @param ?array<string, mixed> $cmd
     * @param bool|string $directCall
     * @return void
     */
    public static function send($content, $next, $context = '', $echo = true, $cmd = null, $directCall = false) {}

    /**
     * @param int $col
     * @param int $line
     * @return string
     */
    public static function setPos($col, $line) {}

    /**
     * @param string $txt
     * @param bool $blink
     * @return string
     */
    public static function writeLine0($txt, $blink = false) {}

    /** @return string */
    public static function clearScreen() {}

    /**
     * @param string $char
     * @param int $num
     * @return string
     */
    public static function repeatChar($char, $num) {}

    /**
     * @param int $line
     * @param string $text
     * @param string $attr
     * @return string
     */
    public static function writeCentered($line, $text, $attr = '') {}

    /**
     * @param string $str
     * @return string
     */
    public static function toG2($str) {}

    /**
     * @param int $posX
     * @param int $posY
     * @param int $length
     * @param int $validWith
     * @param bool $cursor
     * @param string $spaceChar
     * @param string $char
     * @param string $preFill
     * @return array<string, mixed>
     */
    public static function createInputTxtCmd($posX = 1, $posY = 1, $length = 1, $validWith = 128, $cursor = true, $spaceChar = ' ', $char = '', $preFill = '') {}

    /**
     * @param int $posX
     * @param int $posY
     * @param int $width
     * @param int $height
     * @param int $validWith
     * @param bool $cursor
     * @param string $spaceChar
     * @param string[] $preFill
     * @return array<string, mixed>
     */
    public static function createInputMsgCmd($posX = 1, $posY = 1, $width = 1, $height = 1, $validWith = 128, $cursor = true, $spaceChar = ' ', $preFill = []) {}
}
