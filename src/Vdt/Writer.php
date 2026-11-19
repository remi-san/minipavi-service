<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Screen\Zones;

/**
 * Writes Videotex content cell by cell: positions the cursor only where the next character
 * isn't already, sends only the attributes that changed, and repeats characters with REP.
 *
 * It keeps the zones of the text it writes (Zone): a space it is given is written as a
 * delimiter of its zone when it would be one anyway, because a zone attribute it sent waits
 * (a positioning sets the background back, which is sent again), or when the zone the cells
 * before it are in isn't its own (its delimiter wasn't written). Such a space isn't underlined
 * itself, as a delimiter isn't, but the text after it is in its zone again.
 */
final class Writer
{
    private string $vdt = '';
    // Where the next character would go, and with which attributes (null: unknown)
    private ?int $row = null;
    private ?int $col = null;
    private Attributes $attributes;
    // The zone attributes as what was sent leaves them, and the zones of what was written
    private Zone $zone;
    private Zones $zones;
    // Last character sent, and how many more times it is to be repeated
    private ?string $pending = null;
    private int $repeat = 0;

    public function __construct()
    {
        $this->attributes = Attributes::defaults();
        $this->zone = Zone::none();
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
        $this->zones->draw($row, $col, $width, $attributes);
        $this->movePast($col, $width);
    }

    /**
     * A delimiter: a space starting zone, made one by ESC Z or ESC Y just before it
     * (Zone::delimiterCodes())
     */
    public function delimiter(int $row, int $col, int $width, Attributes $attributes, Zone $zone): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $this->delimit($row, $col, $width, $zone);
    }

    /**
     * CAN at row, col: clears to the end of the row, the cells in zone, the zone before them,
     * or the first a delimiter starting zone delimiter, if not null, made one by ESC Z or ESC Y
     * just before it (Zone::delimiterCodes()). As with a space, the first is a delimiter of
     * zone when the cells would lose it otherwise (glyph())
     */
    public function clearLine(int $row, int $col, Attributes $attributes, Zone $zone, ?Zone $delimiter): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $cleared = $delimiter ?? $zone;
        $delimits = $delimiter !== null || $this->losesZone($row, $col, $attributes, $cleared);
        if ($delimits) {
            $this->follow($cleared->delimiterCodes(), $attributes);
            $this->send($cleared->delimiterCodes());
            $this->zone = $this->zone->delimited();
        }
        $this->send(Videotex::CLEAR_LINE);
        for ($i = $col; $i <= ScreenSize::COLS; $i++) {
            $this->zones->draw($row, $i, width: 1, attributes: $attributes);
        }
        if ($delimits) {
            $this->zones->text($row, $col, width: 1, delimiter: $this->zone);
        }
    }

    public function clear(): void
    {
        $this->send(Videotex::CLEAR_SCREEN);
        $this->row = 1;
        $this->col = 1;
        $this->attributes = Attributes::defaults();
        $this->zone = Zone::none();
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

    // Whether a text space at row, col (at the cursor, its attributes sent) loses zone: it is a
    // delimiter anyway, as a zone attribute waits, or the cells before it are in another zone
    private function losesZone(int $row, int $col, Attributes $attributes, Zone $zone): bool
    {
        if ($attributes->isMosaic()) {
            return false;
        }

        return $this->zone->isWaiting() || $this->zones->before($row, $col)->isUnderlined() !== $zone->isUnderlined();
    }

    // Writes a delimiter starting zone at row, col (at the cursor, its attributes sent)
    private function delimit(int $row, int $col, int $width, Zone $zone): void
    {
        $this->follow($zone->delimiterCodes(), $this->attributes);
        $this->send($zone->delimiterCodes());
        $this->put(' ');
        $this->zone = $this->zone->delimited();
        $this->zones->text($row, $col, $width, delimiter: $this->zone);
        $this->movePast($col, $width);
    }

    // Follows the zone attributes codes sent with these attributes set (the charset, SI or SO,
    // first, then ESC <byte>s: Attributes::codesFrom(), Zone::delimiterCodes())
    private function follow(string $codes, Attributes $attributes): void
    {
        $bytes = explode(Videotex::ESC, $codes);
        $charset = array_shift($bytes);
        if ($charset !== '') {
            $this->zone = $this->zone->withCharset($charset);
        }
        foreach ($bytes as $byte) {
            $this->zone = $this->zone->with($byte, $attributes);
        }
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
        $this->attributes = Attributes::defaults();
        $this->zone = Zone::none();
    }

    // Sends the attributes that differ from the current ones
    private function setAttributes(Attributes $attributes): void
    {
        $codes = $attributes->codesFrom($this->attributes);
        $this->attributes = $attributes;
        if ($codes === '') {
            return;
        }

        $this->follow($codes, $attributes);
        $this->send($codes);
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
