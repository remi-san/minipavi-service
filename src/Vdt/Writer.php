<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

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
    private array $attributes = Attributes::DEFAULTS;
    // Last character sent, and how many more times it is to be repeated
    private ?string $pending = null;
    private int $repeat = 0;

    public function glyph(int $row, int $col, string $bytes, int $width, array $attributes): void
    {
        $moved = $this->moveTo($row, $col);
        $changed = $this->setAttributes($attributes);
        if (!$moved && !$changed && $this->pending === $bytes && $this->repeat < 63) {
            $this->repeat++;
        } else {
            $this->flush();
            $this->vdt .= $bytes;
            $this->pending = $bytes;
        }
        $this->col += $width;
        if ($this->col > Screen::COLS) {
            // The screen wraps there, but the next character may be elsewhere
            $this->row = $this->col = null;
        }
    }

    public function clearLine(int $row, int $col, array $attributes): void
    {
        $this->moveTo($row, $col);
        $this->setAttributes($attributes);
        $this->send("\x18");
    }

    public function clear(): void
    {
        $this->send("\x0C");
        $this->row = $this->col = 1;
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

    // Positions the cursor if needed, which resets the attributes; true if it did
    private function moveTo(int $row, int $col): bool
    {
        if ($this->row === $row && $this->col === $col) {
            return false;
        }
        $this->send("\x1F" . chr(0x40 + $row) . chr(0x40 + $col));
        $this->row = $row;
        $this->col = $col;
        $this->attributes = Attributes::DEFAULTS;
        return true;
    }

    // Sends the attributes that differ from the current ones; true if there were some
    private function setAttributes(array $attributes): bool
    {
        $codes = '';
        if ($attributes['charset'] !== $this->attributes['charset']) {
            $codes .= $attributes['charset'];
        }
        foreach ($attributes as $group => $value) {
            if ($group != 'charset' && $value !== $this->attributes[$group]) {
                $codes .= "\x1B" . $value;
            }
        }
        $this->attributes = $attributes;
        if ($codes === '') {
            return false;
        }
        $this->send($codes);
        return true;
    }

    private function send(string $bytes): void
    {
        $this->flush();
        $this->vdt .= $bytes;
    }

    // Sends the repetitions of the last character: REP, or the character again when shorter
    private function flush(): void
    {
        if ($this->repeat > 0) {
            $this->vdt .= $this->repeat * strlen($this->pending) <= 2
                ? str_repeat($this->pending, $this->repeat)
                : "\x12" . chr(0x40 + $this->repeat);
        }
        $this->pending = null;
        $this->repeat = 0;
    }
}
