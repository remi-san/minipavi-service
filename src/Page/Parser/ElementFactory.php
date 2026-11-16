<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Page\Display\{Background, Color, Element, EndKey, Image, Key, Line, Size, Text};
use MiniPaviService\Page\Input;
use MiniPaviService\Vdt\Vdt;

/**
 * Builds display elements and inputs from the attributes and text of the page XML
 * format (documented in the framework's README.md). Used by PageXmlParser for static pages, and by
 * TemplatePage at display time, once placeholders are replaced.
 * Invalid values throw a PageXmlException (or an InvalidArgumentException from the
 * elements), without location: callers add it.
 */
final class ElementFactory
{
    // Tags of the display elements
    public const TAGS = ['background','image','text','line','key','endkey'];

    // Keys for the validwith attribute of <input>, separated by spaces
    private const KEYS = [
        'sommaire' => MSK_SOMMAIRE, 'annulation' => MSK_ANNULATION, 'retour' => MSK_RETOUR,
        'repetition' => MSK_REPETITION, 'guide' => MSK_GUIDE, 'correction' => MSK_CORRECTION,
        'suite' => MSK_SUITE, 'envoi' => MSK_ENVOI,
    ];

    private const LINE_CHARS = ['top' => Line::TOP, 'middle' => Line::MIDDLE, 'bottom' => Line::BOTTOM];

    // Pictures of <image>, by file, read once: templates build their elements on each display
    /** @var array<string, Vdt> */
    private array $images = [];

    /**
     * @param string $baseDir folder of the <background> and <image> files (and their subfolders)
     */
    public function __construct(
        private string $baseDir,
    ) {}

    /**
     * Element for a tag of TAGS.
     *
     * @param array<string, string> $attributes name => value
     * @param string $text content of the XML element
     */
    public function element(string $tag, array $attributes, string $text): Element
    {
        return match ($tag) {
            'background' => Background::fromFile($this->file($text)),
            'image' => new Image(
                $this->image(trim($text)),
                row: $this->int($attributes, 'row', 1),
                col: $this->int($attributes, 'col', 1),
            ),
            'text' => new Text(
                $text,
                row: $this->int($attributes, 'row', 1),
                col: $this->int($attributes, 'col', 1),
                centered: $this->choice($attributes, 'align', ['left' => false,'center' => true], false),
                color: $this->color($attributes, 'color') ?? Color::White,
                bg: $this->color($attributes, 'bg'),
                size: $this->size($attributes),
                fill: $this->choice($attributes, 'fill', ['yes' => true,'no' => false], false),
            ),
            'line' => new Line(
                row: $this->int($attributes, 'row', 1),
                col: $this->int($attributes, 'col', 1),
                length: isset($attributes['length']) ? $this->int($attributes, 'length', 0) : null,
                char: $this->lineChar($attributes),
                color: $this->color($attributes, 'color') ?? Color::White,
            ),
            'key' => new Key(
                $text,
                row: $this->int($attributes, 'row', 1),
                col: $this->int($attributes, 'col', 1),
                color: $this->color($attributes, 'color') ?? Color::Green,
                bg: $this->color($attributes, 'bg'),
            ),
            'endkey' => new EndKey(
                row: isset($attributes['row']) ? $this->int($attributes, 'row', 0) : null,
                color: $this->color($attributes, 'color') ?? Color::Green,
            ),
            default => throw new PageXmlException('unknown element'),
        };
    }

    /**
     * Picture of an <image>
     */
    private function image(string $file): Vdt
    {
        $file = $this->file($file);
        return $this->images[$file] ??= Vdt::fromFile($file);
    }

    /**
     * Path of a <background> or <image> file, relative to the page's folder: only files
     * from that folder or its subfolders (e.g. images/logo.vdt), since in a template the
     * file name may come from the data
     */
    private function file(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new PageXmlException('the file is missing');
        }
        if (str_starts_with($path, '/') || in_array('..', explode('/', str_replace('\\', '/', $path)), true)) {
            throw new PageXmlException("$path: only files from the page's folder or its subfolders");
        }
        return $this->baseDir . '/' . $path;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function input(array $attributes): Input
    {
        $keys = preg_split('/\s+/', trim($attributes['validwith'] ?? 'envoi'));
        if ($keys === false) {
            throw new PageXmlException('validwith: ' . preg_last_error_msg());
        }
        $validWith = 0;
        foreach ($keys as $key) {
            if (!isset(self::KEYS[$key])) {
                throw new PageXmlException("unknown validwith key \"$key\" (" . implode(', ', array_keys(self::KEYS)) . ')');
            }
            $validWith |= self::KEYS[$key];
        }
        return new Input(
            row: $this->int($attributes, 'row', 13),
            col: $this->int($attributes, 'col', 1),
            length: isset($attributes['length']) ? $this->int($attributes, 'length', 0) : null,
            lines: $this->int($attributes, 'lines', 2),
            validWith: $validWith,
        );
    }

    /**
     * Value of a number attribute, or default if absent
     *
     * @param array<string, string> $attributes
     */
    public function int(array $attributes, string $attr, int $default): int
    {
        if (!isset($attributes[$attr])) {
            return $default;
        }
        $value = trim($attributes[$attr]);
        if (!preg_match('/^\d+$/', $value)) {
            throw new PageXmlException("$attr=\"$value\" is not a number");
        }
        return (int) $value;
    }

    /**
     * @template T
     * @param array<string, string> $attributes
     * @param array<string, T> $values value of the attribute => result
     * @param T $default
     * @return T
     */
    private function choice(array $attributes, string $attr, array $values, mixed $default): mixed
    {
        if (!isset($attributes[$attr])) {
            return $default;
        }
        $value = $attributes[$attr];
        if (!array_key_exists($value, $values)) {
            throw new PageXmlException("$attr=\"$value\" must be " . implode(' or ', array_keys($values)));
        }
        return $values[$value];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function color(array $attributes, string $attr): ?Color
    {
        if (!isset($attributes[$attr])) {
            return null;
        }
        return Color::tryFrom($attributes[$attr])
            ?? throw new PageXmlException("unknown $attr \"" . $attributes[$attr] . '" (' . implode(', ', array_column(Color::cases(), 'value')) . ')');
    }

    /**
     * @param array<string, string> $attributes
     */
    private function size(array $attributes): ?Size
    {
        if (!isset($attributes['size'])) {
            return null;
        }
        return Size::tryFrom($attributes['size'])
            ?? throw new PageXmlException('unknown size "' . $attributes['size'] . '" (' . implode(', ', array_column(Size::cases(), 'value')) . ')');
    }

    /**
     * @param array<string, string> $attributes
     */
    private function lineChar(array $attributes): string
    {
        $char = $attributes['char'] ?? 'middle';
        return self::LINE_CHARS[$char] ?? $char;
    }
}
