<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;

/**
 * Writes Videotex content cell by cell: positions the cursor only where the next character
 * isn't already, sends only the attributes that changed, and repeats characters with REP
 */
final class Writer
{
    private string $vdt = '';
    // Where the next character would go, and with which attributes (null: unknown)
    private ?int $row = null;
    private ?int $col = null;
    private Attributes $attributes;
    // Last character sent, and how many more times it is to be repeated
    private ?string $pending = null;
    private int $repeat = 0;

    public function __construct()
    {
        $this->attributes = Attributes::defaults();
    }

    public function glyph(int $row, int $col, string $bytes, int $width, Attributes $attributes): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $this->put($bytes);
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
        $this->send($zone->delimiterCodes());
        $this->put(' ');
        $this->movePast($col, $width);
    }

    /**
     * CAN at row, col: clears to the end of the row, the first cell a delimiter starting zone
     * delimiter, if not null, made one by ESC Z or ESC Y just before it (Zone::delimiterCodes())
     */
    public function clearLine(int $row, int $col, Attributes $attributes, ?Zone $delimiter): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        if ($delimiter !== null) {
            $this->send($delimiter->delimiterCodes());
        }
        $this->send(Videotex::CLEAR_LINE);
    }

    public function clear(): void
    {
        $this->send(Videotex::CLEAR_SCREEN);
        $this->row = 1;
        $this->col = 1;
        $this->attributes = Attributes::defaults();
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
        if ($this->pending === $bytes && $this->repeat < 63) {
            $this->repeat++;
            return;
        }
        $this->flush();
        $this->vdt .= $bytes;
        $this->pending = $bytes;
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
    }

    // Sends the attributes that differ from the current ones
    private function setAttributes(Attributes $attributes): void
    {
        $codes = $attributes->codesFrom($this->attributes);
        $this->attributes = $attributes;
        if ($codes === '') {
            return;
        }

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
