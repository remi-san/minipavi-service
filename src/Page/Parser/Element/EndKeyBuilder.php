<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Page\Display\Color;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\EndKey;
use MiniPaviService\Page\Parser\AttributeValues;

/**
 * <endkey row color/>: how to come back to the screen, its labels the page's messages
 * (FrameworkMessage::EndKey, EndKeyShift). Once per page: not inside <foreach>.
 */
final readonly class EndKeyBuilder implements ElementBuilder
{
    /**
     * @param Messages $messages of the page
     */
    public function __construct(
        private Messages $messages,
    ) {}

    public function tag(): string
    {
        return 'endkey';
    }

    public function build(AttributeValues $attributes, string $text): Element
    {
        return new EndKey(
            $this->messages->get(FrameworkMessage::EndKey),
            $this->messages->get(FrameworkMessage::EndKeyShift),
            row: $attributes->optionalInt('row'),
            color: $attributes->enum('color', Color::class) ?? Color::Green,
        );
    }

    public function isPlacedByRow(): bool
    {
        return true;
    }

    public function isAllowedInLoop(): bool
    {
        return false;
    }
}
