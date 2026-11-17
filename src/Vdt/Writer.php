<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Screen;

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
    /** @var array<string, string> */
    private array $attributes = Attributes::DEFAULTS;
    // Last character sent, and how many more times it is to be repeated
    private ?string $pending = null;
    private int $repeat = 0;

    /**
     * @param array<string, string> $attributes
     */
    public function glyph(int $row, int $col, string $bytes, int $width, array $attributes): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $this->put($bytes);
        // At col since moveTo()
        $this->col = $col + $width;
        if ($this->col > Screen::COLS) {
            // The screen wraps there, but the next character may be elsewhere
            $this->row = null;
            $this->col = null;
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    public function clearLine(int $row, int $col, array $attributes): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $this->send("\x18");
    }

    public function clear(): void
    {
        $this->send("\x0C");
        $this->row = 1;
        $this->col = 1;
        $this->attributes = Attributes::DEFAULTS;
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

    // Positions the cursor if needed, which resets the attributes
    private function moveTo(int $row, int $col): void
    {
        if ($this->row === $row && $this->col === $col) {
            return;
        }
        $this->send("\x1F" . chr(0x40 + $row) . chr(0x40 + $col));
        $this->row = $row;
        $this->col = $col;
        $this->attributes = Attributes::DEFAULTS;
    }

    // Sends the attributes that differ from the current ones
    /**
     * @param array<string, string> $attributes
     */
    private function setAttributes(array $attributes): void
    {
        $codes = '';
        if ($attributes['charset'] !== $this->attributes['charset']) {
            $codes .= $attributes['charset'];
        }
        foreach ($attributes as $group => $value) {
            if ($group === 'charset' || $value === $this->attributes[$group]) {
                continue;
            }

            $codes .= "\x1B" . $value;
        }
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
                : "\x12" . chr(0x40 + $this->repeat);
        }
        $this->pending = null;
        $this->repeat = 0;
    }
}
