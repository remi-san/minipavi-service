<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Vdt\Vdt;

/**
 * How a page sets up the Minitel, sent before its content (attributes keyboard, echo and
 * scroll of <page>): each setting is left as it is when null
 */
final readonly class Setup
{
    public function __construct(
        private ?Keyboard $keyboard = null,
        private ?LocalEcho $localEcho = null,
        private ?Scroll $scroll = null,
    ) {}

    /**
     * Protocol sequences of the settings given, in this order: keyboard, echo, scroll
     */
    public function toVdt(): Vdt
    {
        return new Vdt(
            ($this->keyboard?->sequence() ?? '')
            . ($this->localEcho?->sequence() ?? '')
            . ($this->scroll?->sequence() ?? ''),
        );
    }
}
