<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Screen\Attribute;

/**
 * The character set: SI (text, G0) or SO (mosaic, G1), sent as its byte alone (code()), not
 * after ESC as an Attribute is. Switching to one sets the character attributes back
 * (Attributes::withCharset()), and SO applies the background colour waiting
 * (Zone::attributesAfter()).
 */
enum Charset: string
{
    case Text = "\x0F";
    case Mosaic = "\x0E";

    /**
     * The byte selecting this character set: SI or SO
     */
    public function code(): string
    {
        return $this->value;
    }
}
