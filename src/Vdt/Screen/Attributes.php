<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attribute\Attribute;
use MiniPaviService\Vdt\Screen\Attribute\BackgroundColor;
use MiniPaviService\Vdt\Screen\Attribute\Blinking;
use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attribute\Inversion;
use MiniPaviService\Vdt\Screen\Attribute\Masking;
use MiniPaviService\Vdt\Screen\Attribute\TextColor;
use MiniPaviService\Vdt\Screen\Attribute\Underlining;
use MiniPaviService\Vdt\Videotex;

/**
 * Character attributes of a cell, as Videotex content sets them: the charset (text or mosaic),
 * the colours, the size, blinking, masking, underlining and inverse video. A positioning (US),
 * a home (RS) or a clear screen (FF) sets them back to defaults(). Each Attribute sets one
 * (with()). Immutable.
 *
 * Underlining is the separation of mosaic characters. In text, it is a zone's (Zone), as the
 * background colour and masking are: the cursor doesn't set them there, and the Screen's cells
 * have those their zone shows. The cursor's background colour is the last one applied: what a
 * mosaic character is drawn on, and a delimiter with no background colour waiting.
 */
final readonly class Attributes
{
    /**
     * @param string $charset SI (text) or SO (mosaic)
     */
    private function __construct(
        private string $charset,
        private Color $foreground,
        private Color $background,
        private CharacterSize $size,
        private Blinking $blinking,
        private Masking $masking,
        private Underlining $underlining,
        private Inversion $inversion,
    ) {}

    /**
     * Attributes of the Minitel as it starts: white text on black, normal size, in text (SI),
     * and every other attribute off
     */
    public static function defaults(): self
    {
        return new self(
            Videotex::TEXT_SET,
            Color::White,
            Color::Black,
            CharacterSize::Normal,
            Blinking::Fixed,
            Masking::Unmask,
            Underlining::NoUnderline,
            Inversion::NoInverse,
        );
    }

    /**
     * These attributes, with the one attribute sets (Attribute::appliedTo())
     */
    public function with(Attribute $attribute): self
    {
        return $attribute->appliedTo($this);
    }

    /**
     * These attributes, with this text (foreground) colour
     */
    public function withForeground(Color $foreground): self
    {
        return $this->copy(foreground: $foreground);
    }

    /**
     * These attributes, with this background colour
     */
    public function withBackground(Color $background): self
    {
        return $this->copy(background: $background);
    }

    public function withSize(CharacterSize $size): self
    {
        return $this->copy(size: $size);
    }

    public function withBlinking(Blinking $blinking): self
    {
        return $this->copy(blinking: $blinking);
    }

    public function withMasking(Masking $masking): self
    {
        return $this->copy(masking: $masking);
    }

    /**
     * These attributes, underlined (separated, in mosaic) or not
     */
    public function withUnderlining(Underlining $underlining): self
    {
        return $this->copy(underlining: $underlining);
    }

    public function withInversion(Inversion $inversion): self
    {
        return $this->copy(inversion: $inversion);
    }

    /**
     * These attributes, in the charset SO (mosaic) or SI (text) selects, as the Minitel sets
     * them on every SO and SI, even one that doesn't change the charset (MiniPavi's emulator,
     * setCharType(), #26): normal size, no inverse video, and no separated mosaic. Underline in
     * text is a zone's (Zone), never a character attribute, so neither SO nor SI keeps one
     */
    public function withCharset(string $byte): self
    {
        return $this->copy(
            charset: $byte === Videotex::MOSAIC_SET ? Videotex::MOSAIC_SET : Videotex::TEXT_SET,
            size: CharacterSize::Normal,
            underlining: Underlining::NoUnderline,
            inversion: Inversion::NoInverse,
        );
    }

    public function isMosaic(): bool
    {
        return $this->charset === Videotex::MOSAIC_SET;
    }

    public function foreground(): Color
    {
        return $this->foreground;
    }

    public function background(): Color
    {
        return $this->background;
    }

    public function isDoubleHeight(): bool
    {
        return $this->size->isTall();
    }

    public function isDoubleWidth(): bool
    {
        return $this->size->isWide();
    }

    public function isBlinking(): bool
    {
        return $this->blinking === Blinking::Blink;
    }

    public function isMasked(): bool
    {
        return $this->masking === Masking::Mask;
    }

    public function isUnderlined(): bool
    {
        return $this->underlining === Underlining::Underline;
    }

    /**
     * Whether a mosaic character is drawn with separated pixels: underlining, in mosaic
     */
    public function isSeparated(): bool
    {
        return $this->isMosaic() && $this->isUnderlined();
    }

    public function isInverse(): bool
    {
        return $this->inversion === Inversion::Inverse;
    }

    /**
     * Whether ESC M (double height) and ESC O (double size) are accepted on row: not on rows
     * 0 and 1, where the Minitel discards them (checked in MiniPavi's emulator, #172)
     */
    public static function allowsDoubleHeight(int $row): bool
    {
        return $row > 1;
    }

    /**
     * These attributes, with a size that can be set on row: where ESC M and ESC O would be
     * discarded, double height becomes normal size and double size double width (ESC N is
     * accepted on row 1, CharacterSize::flattened()), so that a character written there takes
     * the columns it is given
     */
    public function writableOn(int $row): self
    {
        if (self::allowsDoubleHeight($row)) {
            return $this;
        }

        return $this->flattened();
    }

    /**
     * These attributes, at normal height: double height becomes normal size and double size
     * double width (CharacterSize::flattened()), a character written with them taking the
     * columns, but not the row above, it would
     */
    public function flattened(): self
    {
        return $this->copy(size: $this->size->flattened());
    }

    /**
     * Columns taken by a character: double width only applies to text characters
     */
    public function width(): int
    {
        return $this->isDoubleWidth() && !$this->isMosaic() ? 2 : 1;
    }

    /**
     * Rows taken by a character: double height only applies to text characters, as on the
     * Minitel (which ignores ESC M and ESC O for mosaic)
     */
    public function height(): int
    {
        return $this->isDoubleHeight() && !$this->isMosaic() ? 2 : 1;
    }

    /**
     * The charset: SI (text) or SO (mosaic)
     */
    public function charset(): string
    {
        return $this->charset;
    }

    /**
     * The attributes setting these where current ones are, after the charset (charset(),
     * which SI and SO set: withCharset()): each that differs, in this order: the text colour,
     * the background colour, the size, blinking, masking, underlining and inverse video
     *
     * @return list<Attribute>
     */
    public function changesFrom(self $current): array
    {
        if ($this->charset !== $current->charset) {
            $current = $current->withCharset($this->charset);
        }
        $was = $current->attributeList();
        $changes = [];
        foreach ($this->attributeList() as $i => $attribute) {
            if ($attribute->code() === $was[$i]->code()) {
                continue;
            }
            $changes[] = $attribute;
        }

        return $changes;
    }

    /**
     * Each of these attributes, but the charset, in the order changesFrom() gives them
     *
     * @return list<Attribute>
     */
    private function attributeList(): array
    {
        return [
            new TextColor($this->foreground),
            new BackgroundColor($this->background),
            $this->size,
            $this->blinking,
            $this->masking,
            $this->underlining,
            $this->inversion,
        ];
    }

    private function copy(
        ?string $charset = null,
        ?Color $foreground = null,
        ?Color $background = null,
        ?CharacterSize $size = null,
        ?Blinking $blinking = null,
        ?Masking $masking = null,
        ?Underlining $underlining = null,
        ?Inversion $inversion = null,
    ): self {
        return new self(
            $charset ?? $this->charset,
            $foreground ?? $this->foreground,
            $background ?? $this->background,
            $size ?? $this->size,
            $blinking ?? $this->blinking,
            $masking ?? $this->masking,
            $underlining ?? $this->underlining,
            $inversion ?? $this->inversion,
        );
    }
}
