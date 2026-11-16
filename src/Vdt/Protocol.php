<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

/**
 * Protocol sequences configuring the Minitel (PRO1 to PRO3): the framework's names for the
 * PRO_* constants of MiniPaviCli. Typically sent before a page, e.g.
 * Protocol::KEYBOARD_LOWERCASE . Protocol::LOCAL_ECHO_OFF . $page->display()
 */
final class Protocol
{
    // Letters typed in lowercase (the Minitel starts in uppercase)
    public const KEYBOARD_LOWERCASE = "\x1B\x3A\x69\x45";
    // What is typed is not shown by the Minitel itself: the gateway's input commands show it
    public const LOCAL_ECHO_OFF = "\x1B\x3B\x60\x58\x51";
    // Page mode: the screen doesn't scroll up past the last row
    public const SCROLL_OFF = "\x1B\x3A\x6A\x43";
}
