<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Element;

use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Building\Placement;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\EndKey;

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

    // Once per page
    public function checkPlacement(Placement $placement): void
    {
        $placement->refuseInLoop();
    }

    public function build(AttributeValues $attributes, string $text, Placement $placement): Element
    {
        // Never in a <foreach> (checkPlacement()): its row is the page's
        return new EndKey(
            $this->messages->get(FrameworkMessage::EndKey),
            $this->messages->get(FrameworkMessage::EndKeyShift),
            self::position($attributes->optionalInt('row')),
            color: $attributes->enum('color', ...Color::cases()) ?? Color::Green,
        );
    }

    /**
     * Row of the key; without one, the bottom of the screen (EndKey)
     */
    private static function position(?int $row): ?Position
    {
        return $row === null ? null : new Position($row);
    }
}
