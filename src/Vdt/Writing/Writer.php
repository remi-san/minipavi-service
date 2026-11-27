<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Writing;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attribute\Attribute;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;
use MiniPaviService\Vdt\Videotex;

/**
 * Writes Videotex content cell by cell: positions the cursor only where the next character
 * isn't already, sends only the attributes that changed, and repeats characters with REP.
 * It writes the character printed before the content (ScreenListener::priorCharacter()) with
 * REP too, which repeats it as long as nothing was printed before.
 *
 * It keeps the zones of the text it writes (Zone): their background, masking and underline
 * are sent only before a delimiter, as on the Minitel they wait for one (a text character's
 * background isn't sent with it). A space it is given is written as a delimiter of its zone
 * when the zone the cells before it are in isn't its own (its delimiter wasn't written). Such
 * a space isn't underlined itself, as a delimiter isn't, but the text after it is in its zone
 * again.
 */
final class Writer
{
    private string $vdt = '';
    // Where the next character would go (null: unknown)
    private ?int $row = null;
    private ?int $col = null;
    // The attributes and the zone attributes as what was sent leaves them (the Minitel's
    // cursor), and the zones of what was written
    private Cursor $cursor;
    private Zones $zones;
    // Last character sent, and how many more times it is to be repeated
    private ?string $pending = null;
    /** @var non-negative-int */
    private int $repeat = 0;
    // How many times the character printed before the content is to be written with REP
    /** @var non-negative-int */
    private int $priors = 0;

    public function __construct()
    {
        $this->cursor = new Cursor();
        $this->zones = new Zones();
    }

    /**
     * A character at position, with these attributes, which give its width: a text one in
     * zone, the zone of the cells before it (a space may be written as its delimiter, see
     * above)
     */
    public function glyph(Position $position, string $bytes, Attributes $attributes, Zone $zone): void
    {
        $this->moveTo($position);
        $this->sendChangesTo($attributes);
        if ($bytes === ' ' && $this->losesZone($position, $attributes, $zone)) {
            $this->delimit($position, $zone);
            return;
        }
        $this->put($bytes);
        $this->zones->draw($position, $this->cursor->attributes());
        $this->movePast($position);
    }

    /**
     * A delimiter: a space starting zone, made one by the zone attributes sent just before it
     * (Zone::delimiterAttributesFrom())
     */
    public function delimiter(Position $position, Attributes $attributes, Zone $zone): void
    {
        $this->moveTo($position);
        $this->sendChangesTo($attributes);
        $this->delimit($position, $zone);
    }

    /**
     * The character printed before the content at position, which can't be known
     * (ScreenListener::priorCharacter()): written with REP, which repeats it if nothing was
     * printed before it, a delimiter starting delimiter, if not null, made one by the zone
     * attributes sent just before it (Zone::delimiterAttributesFrom()), as a space would be
     */
    public function priorCharacter(Position $position, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->moveTo($position);
        $this->sendChangesTo($attributes);
        if ($delimiter === null) {
            $this->putPrior();
            $this->zones->draw($position, $this->cursor->attributes());
            $this->movePast($position);
            return;
        }
        $this->sendZone($delimiter);
        $this->putPrior();
        $this->cursor->delimit();
        $this->zones->delimiter($position, $this->cursor->attributes(), $this->cursor->zone());
        $this->movePast($position);
    }

    /**
     * CAN at position: clears to the end of the row, the cells in zone, the zone before them,
     * or the first a delimiter starting zone delimiter, if not null, made one by the zone
     * attributes sent just before it (Zone::delimiterAttributesFrom()). As with a space, the
     * first is a delimiter of zone when the cells would lose it otherwise (glyph())
     */
    public function clearLine(Position $position, Attributes $attributes, Zone $zone, ?Zone $delimiter): void
    {
        $this->moveTo($position);
        $this->sendChangesTo($attributes);
        $cleared = $delimiter ?? $zone;
        $delimits = $delimiter !== null || $this->losesZone($position, $attributes, $cleared);
        if ($delimits) {
            $this->sendZone($cleared);
            $this->cursor->delimit();
        }
        $this->send(Videotex::CLEAR_LINE);
        $this->zones->clearLine($position, $this->cursor->attributes(), $delimits ? $this->cursor->zone() : null);
    }

    public function clear(): void
    {
        $this->send(Videotex::CLEAR_SCREEN);
        $this->row = 1;
        $this->col = 1;
        $this->cursor = new Cursor();
        $this->zones->clear();
    }

    public function raw(string $bytes): void
    {
        $this->send($bytes);
    }

    public function vdt(): string
    {
        $this->flush();
        return $this->vdt;
    }

    // A character, or one more repetition of the last one sent: sending anything else (a
    // position, attributes) sends the repetitions first (flush()). They stop at the end of
    // the row (movePast(): the next character is positioned again), so they are fewer than
    // the most a REP gives (Videotex::MAX_REPEAT)
    private function put(string $bytes): void
    {
        if ($this->pending === $bytes) {
            $this->repeat++;
            return;
        }
        $this->flush();
        $this->vdt .= $bytes;
        $this->pending = $bytes;
    }

    // One more repetition of the character printed before the content: sending anything
    // else sends the REP first (flush()). Nothing was printed before it, so no other
    // character waits to be repeated
    private function putPrior(): void
    {
        $this->priors++;
    }

    // Whether a text space at position (at the cursor, its attributes sent) loses zone: the
    // cells before it are in another zone
    private function losesZone(Position $position, Attributes $attributes, Zone $zone): bool
    {
        return !$attributes->isMosaic() && !$this->zones->before($position->row, $position->col)->showsAs($zone);
    }

    // Writes a delimiter starting zone at position (at the cursor, its attributes sent)
    private function delimit(Position $position, Zone $zone): void
    {
        $this->sendZone($zone);
        $this->put(' ');
        $this->cursor->delimit();
        $this->zones->delimiter($position, $this->cursor->attributes(), $this->cursor->zone());
        $this->movePast($position);
    }

    // Sends the zone attributes making the next space a delimiter starting zone
    private function sendZone(Zone $zone): void
    {
        $this->sendAttributes($zone->delimiterAttributesFrom($this->cursor->zone(), $this->cursor->attributes()));
    }

    // Sends attributes (Attributes::changesFrom(), Zone::delimiterAttributesFrom()), the
    // cursor following them as the Minitel does
    /** @param list<Attribute> $attributes */
    private function sendAttributes(array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $this->cursor->receive($attribute);
            $this->send($attribute->code());
        }
    }

    // Where the next character goes, after one at position (the cursor's since moveTo()), as
    // wide as the attributes sent make it
    private function movePast(Position $position): void
    {
        $this->col = $position->col + $this->cursor->attributes()->width();
        if ($this->col > ScreenSize::COLS) {
            // The screen wraps there, but the next character may be elsewhere
            $this->row = null;
            $this->col = null;
        }
    }

    // Positions the cursor if needed, which resets the attributes
    private function moveTo(Position $position): void
    {
        if ($this->row === $position->row && $this->col === $position->col) {
            return;
        }
        $this->send(Videotex::position($position->row, $position->col));
        $this->row = $position->row;
        $this->col = $position->col;
        $this->cursor->reset($position->row, $position->col);
    }

    // Sends the character attributes that differ from the current ones: in text, the
    // background isn't the character's, but its zone's, sent with its delimiter (delimit())
    private function sendChangesTo(Attributes $attributes): void
    {
        $current = $this->cursor->attributes();
        if (!$attributes->isMosaic()) {
            $attributes = $attributes->withBackground($current->background());
        }
        if ($attributes->charset() !== $current->charset()) {
            $this->cursor->switchCharset($attributes->charset());
            $this->send($attributes->charset());
        }
        $this->sendAttributes($attributes->changesFrom($current));
    }

    private function send(string $bytes): void
    {
        $this->flush();
        $this->vdt .= $bytes;
    }

    // Sends the repetitions of the last character: REP, or the character again when shorter;
    // or those of the character printed before the content, REP
    private function flush(): void
    {
        $repeated = str_repeat($this->pending ?? '', $this->repeat);
        $this->vdt .= \strlen($repeated) <= 2 ? $repeated : Videotex::repeat($this->repeat);
        $this->pending = null;
        $this->repeat = 0;
        if ($this->priors > 0) {
            $this->vdt .= Videotex::repeat($this->priors);
            $this->priors = 0;
        }
    }
}
