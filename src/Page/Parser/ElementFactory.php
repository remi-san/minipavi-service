<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Page\Display\Background;
use MiniPaviService\Page\Display\Color;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\EndKey;
use MiniPaviService\Page\Display\Image;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Page\Display\Line;
use MiniPaviService\Page\Display\Size;
use MiniPaviService\Page\Display\Text;
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
    public const TAGS = ['background', 'image', 'text', 'line', 'key', 'endkey'];

    private const LINE_CHARS = ['top' => Line::TOP, 'middle' => Line::MIDDLE, 'bottom' => Line::BOTTOM];

    // Pictures of <image>, by file, read once: templates build their elements on each display
    /** @var array<string, Vdt> */
    private array $images = [];

    /**
     * @param string $baseDir folder of the <background> and <image> files (and their subfolders)
     * @param Messages $messages of the page, for the texts of <endkey>
     */
    public function __construct(
        private string $baseDir,
        private Messages $messages,
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
                row: $this->int($attributes, attr: 'row', default: 1),
                col: $this->int($attributes, attr: 'col', default: 1),
            ),
            'text' => new Text(
                $text,
                row: $this->int($attributes, attr: 'row', default: 1),
                col: $this->int($attributes, attr: 'col', default: 1),
                centered: $this->choice(
                    $attributes,
                    attr: 'align',
                    values: ['left' => false, 'center' => true],
                    default: false,
                ),
                color: $this->color($attributes, attr: 'color') ?? Color::White,
                bg: $this->color($attributes, attr: 'bg'),
                size: $this->size($attributes),
                fill: $this->choice($attributes, attr: 'fill', values: ['yes' => true, 'no' => false], default: false),
            ),
            'line' => new Line(
                row: $this->int($attributes, attr: 'row', default: 1),
                col: $this->int($attributes, attr: 'col', default: 1),
                length: array_key_exists('length', $attributes)
                    ? $this->int($attributes, attr: 'length', default: 0)
                    : null,
                char: $this->lineChar($attributes),
                color: $this->color($attributes, attr: 'color') ?? Color::White,
            ),
            'key' => new Key(
                $text,
                row: $this->int($attributes, attr: 'row', default: 1),
                col: $this->int($attributes, attr: 'col', default: 1),
                color: $this->color($attributes, attr: 'color') ?? Color::Green,
                bg: $this->color($attributes, attr: 'bg'),
            ),
            'endkey' => new EndKey(
                $this->messages->get(FrameworkMessage::EndKey),
                $this->messages->get(FrameworkMessage::EndKeyShift),
                row: array_key_exists('row', $attributes) ? $this->int($attributes, attr: 'row', default: 0) : null,
                color: $this->color($attributes, attr: 'color') ?? Color::Green,
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
        if (
            str_starts_with($path, '/')
            || in_array('..', explode('/', str_replace('\\', replace: '/', subject: $path)), strict: true)
        ) {
            throw new PageXmlException("{$path}: only files from the page's folder or its subfolders");
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
        // Keys for the validwith attribute of <input>, separated by spaces
        $validWith = [];
        foreach ($keys as $name) {
            $validWith[] = FunctionKey::fromXmlName($name) ?? throw new PageXmlException(
                "unknown validwith key \"{$name}\" ("
                . implode(', ', array_map(static fn(FunctionKey $key) => $key->xmlName(), FunctionKey::cases()))
                . ')',
            );
        }
        return new Input(
            row: $this->int($attributes, attr: 'row', default: 13),
            col: $this->int($attributes, attr: 'col', default: 1),
            length: array_key_exists('length', $attributes)
                ? $this->int($attributes, attr: 'length', default: 0)
                : null,
            lines: $this->int($attributes, attr: 'lines', default: 2),
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
        if (!array_key_exists($attr, $attributes)) {
            return $default;
        }
        $value = trim($attributes[$attr]);
        if (!preg_match('/^\d+$/', $value)) {
            throw new PageXmlException("{$attr}=\"{$value}\" is not a number");
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
        if (!array_key_exists($attr, $attributes)) {
            return $default;
        }
        $value = $attributes[$attr];
        if (!array_key_exists($value, $values)) {
            throw new PageXmlException("{$attr}=\"{$value}\" must be " . implode(' or ', array_keys($values)));
        }
        return $values[$value];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function color(array $attributes, string $attr): ?Color
    {
        if (!array_key_exists($attr, $attributes)) {
            return null;
        }
        return (
            Color::tryFrom($attributes[$attr]) ?? throw new PageXmlException(
                "unknown {$attr} \""
                . $attributes[$attr]
                . '" ('
                . implode(', ', array_column(Color::cases(), 'value'))
                . ')',
            )
        );
    }

    /**
     * @param array<string, string> $attributes
     */
    private function size(array $attributes): ?Size
    {
        if (!array_key_exists('size', $attributes)) {
            return null;
        }
        return (
            Size::tryFrom($attributes['size']) ?? throw new PageXmlException(
                'unknown size "'
                . $attributes['size']
                . '" ('
                . implode(', ', array_column(Size::cases(), 'value'))
                . ')',
            )
        );
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
