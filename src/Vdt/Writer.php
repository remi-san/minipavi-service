<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Cursor;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;

/**
 * Writes Videotex content cell by cell: positions the cursor only where the next character
 * isn't already, sends only the attributes that changed, and repeats characters with REP.
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
    private int $repeat = 0;

    public function __construct()
    {
        $this->cursor = new Cursor();
        $this->zones = new Zones();
    }

    /**
     * A character at row, col, width cells wide: a text one in zone, the zone of the cells
     * before it (a space may be written as its delimiter, see above)
     */
    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        if ($bytes === ' ' && $this->losesZone($row, $col, $attributes, $zone)) {
            $this->delimit($row, $col, $width, $zone);
            return;
        }
        $this->put($bytes);
        $this->zones->draw($row, $col, $width, $this->cursor->attributes());
        $this->movePast($col, $width);
    }

    /**
     * A delimiter: a space starting zone, made one by the zone attributes sent just before it
     * (Zone::delimiterCodesFrom())
     */
    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $this->delimit($row, $col, $width, $zone);
    }

    /**
     * CAN at row, col: clears to the end of the row, the cells in zone, the zone before them,
     * or the first a delimiter starting zone delimiter, if not null, made one by the zone
     * attributes sent just before it (Zone::delimiterCodesFrom()). As with a space, the first
     * is a delimiter of zone when the cells would lose it otherwise (glyph())
     */
    public function clearLine(int $row, int $col, Attributes $attributes, Zone $zone, ?Zone $delimiter): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $cleared = $delimiter ?? $zone;
        $delimits = $delimiter !== null || $this->losesZone($row, $col, $attributes, $cleared);
        if ($delimits) {
            $this->sendZone($cleared);
            $this->cursor->delimit();
        }
        $this->send(Videotex::CLEAR_LINE);
        for ($i = $col; $i <= ScreenSize::COLS; $i++) {
            $this->zones->draw($row, $i, width: 1, attributes: $this->cursor->attributes());
        }
        if ($delimits) {
            $this->zones->text($row, $col, width: 1, delimiter: $this->cursor->zone());
        }
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
    // position, attributes) sends the repetitions first (flush())
    private function put(string $bytes): void
    {
        if ($this->pending === $bytes && $this->repeat < Videotex::MAX_REPEAT) {
            $this->repeat++;
            return;
        }
        $this->flush();
        $this->vdt .= $bytes;
        $this->pending = $bytes;
    }

    // Whether a text space at row, col (at the cursor, its attributes sent) loses zone: the
    // cells before it are in another zone
    private function losesZone(int $row, int $col, Attributes $attributes, Zone $zone): bool
    {
        return !$attributes->isMosaic() && !$this->zones->before($row, $col)->showsAs($zone);
    }

    // Writes a delimiter starting zone at row, col (at the cursor, its attributes sent)
    private function delimit(int $row, int $col, int $width, Zone $zone): void
    {
        $this->sendZone($zone);
        $this->put(' ');
        $this->cursor->delimit();
        $this->zones->text($row, $col, $width, delimiter: $this->cursor->zone());
        $this->movePast($col, $width);
    }

    // Sends the zone attributes making the next space a delimiter starting zone
    private function sendZone(Zone $zone): void
    {
        $this->sendCodes($zone->delimiterCodesFrom($this->cursor->zone(), $this->cursor->attributes()));
    }

    // Sends codes (the charset, SI or SO, first, then ESC <byte>s: Attributes::codesFrom(),
    // Zone::delimiterCodesFrom()), the cursor following them as the Minitel does
    private function sendCodes(string $codes): void
    {
        if ($codes === '') {
            return;
        }
        $bytes = explode(Videotex::ESC, $codes);
        $charset = array_shift($bytes);
        if ($charset !== '') {
            $this->cursor->setCharset($charset);
        }
        foreach ($bytes as $byte) {
            $this->cursor->setAttribute($byte);
        }
        $this->send($codes);
    }

    // Where the next character goes, after one at col, width cells wide (at col since moveTo())
    private function movePast(int $col, int $width): void
    {
        $this->col = $col + $width;
        if ($this->col > ScreenSize::COLS) {
            // The screen wraps there, but the next character may be elsewhere
            $this->row = null;
            $this->col = null;
        }
    }

    // Positions the cursor if needed, which resets the attributes
    private function moveTo(int $row, int $col): void
    {
        if ($this->row === $row && $this->col === $col) {
            return;
        }
        $this->send(Videotex::POSITION . chr(0x40 + $row) . chr(0x40 + $col));
        $this->row = $row;
        $this->col = $col;
        $this->cursor->reset($row, $col);
    }

    // Sends the character attributes that differ from the current ones: in text, the
    // background isn't the character's, but its zone's, sent with its delimiter (delimit())
    private function setAttributes(Attributes $attributes): void
    {
        $current = $this->cursor->attributes();
        if (!$attributes->isMosaic()) {
            $attributes = $attributes->with(Attributes::backgroundColor($current->background()));
        }
        $this->sendCodes($attributes->codesFrom($current));
    }

    private function send(string $bytes): void
    {
        $this->flush();
        $this->vdt .= $bytes;
    }

    // Sends the repetitions of the last character: REP, or the character again when shorter
    private function flush(): void
    {
        // Repetitions are only counted after a character was sent
        if ($this->repeat > 0 && $this->pending !== null) {
            $this->vdt .= ($this->repeat * strlen($this->pending)) <= 2
                ? str_repeat($this->pending, $this->repeat)
                : Videotex::REPEAT . chr(0x40 + $this->repeat);
        }
        $this->pending = null;
        $this->repeat = 0;
    }
}
