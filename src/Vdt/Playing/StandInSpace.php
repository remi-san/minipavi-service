<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Playing;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;

/**
 * The space that stands in, at a position, for the character printed before the
 * content, which a REP before any character repeats and which can't be known
 * (ScreenListener::priorCharacter()): a space with its attributes, a delimiter while a zone
 * attribute waits, as a space drawn there would be. Immutable.
 *
 * @internal
 */
final readonly class StandInSpace
{
    /**
     * @param ?Zone $delimiter the zone it starts as a delimiter; null if it isn't one
     */
    public function __construct(
        private Position $position,
        private Attributes $attributes,
        private ?Zone $delimiter,
    ) {}

    /**
     * Tells it to listener as drawn: a delimiter, if it is one (ScreenListener::delimiter()),
     * a space otherwise (ScreenListener::glyph())
     */
    public function tellTo(ScreenListener $listener): void
    {
        if ($this->delimiter !== null) {
            $listener->delimiter($this->position, $this->attributes, $this->delimiter);
            return;
        }
        $listener->glyph($this->position, ' ', $this->attributes);
    }

    /**
     * Draws it in zones: a delimiter starting its zone (Zones::delimiter()), or a character
     * (Zones::draw())
     */
    public function drawIn(Zones $zones): void
    {
        if ($this->delimiter !== null) {
            $zones->delimiter($this->position, $this->attributes, $this->delimiter);
            return;
        }
        $zones->draw($this->position, $this->attributes);
    }
}
