<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Vdt\Keyboard;
use MiniPaviService\Vdt\LocalEcho;
use MiniPaviService\Vdt\Scroll;

/**
 * How a page sets up the Minitel, sent before its content (attributes keyboard, echo and
 * scroll of <page>): each setting is left as it is when null
 */
final readonly class Setup
{
    public function __construct(
        public ?Keyboard $keyboard = null,
        public ?LocalEcho $localEcho = null,
        public ?Scroll $scroll = null,
    ) {}

    /**
     * Protocol sequences of the settings given, in this order: keyboard, echo, scroll
     */
    public function toVdt(): string
    {
        return ($this->keyboard?->sequence() ?? '') . ($this->localEcho?->sequence() ?? '') . ($this->scroll?->sequence() ?? '');
    }
}
