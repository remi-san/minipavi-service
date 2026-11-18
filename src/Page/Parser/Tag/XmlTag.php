<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Text\Placeholders;
use MiniPaviService\Xml\XmlElements;

/**
 * An element of a page XML file, as a TagParser reads it: its tag, attributes and text, and
 * where it is in the file, for error messages
 */
final readonly class XmlTag
{
    /** @var array<string, string> */
    public array $attributes;

    public string $text;

    /**
     * @param string $at file and line, e.g. "home.xml line 3"
     */
    public function __construct(
        public string $name,
        public \SimpleXMLElement $element,
        public string $at,
    ) {
        $this->attributes = XmlElements::attributes($element);
        $this->text = (string) $element;
    }

    /**
     * The element of a page file named file
     */
    public static function of(string $name, \SimpleXMLElement $element, string $file): self
    {
        return new self($name, $element, $file . ' line ' . dom_import_simplexml($element)->getLineNo());
    }

    public function values(): AttributeValues
    {
        return new AttributeValues($this->attributes);
    }

    /**
     * Whether its attributes or text have {{placeholders}}
     */
    public function hasPlaceholder(): bool
    {
        return (bool) preg_match(Placeholders::PLACEHOLDER, implode("\n", $this->attributes) . "\n" . $this->text);
    }

    /**
     * Error of this element, with its location
     */
    public function error(string $message): PageXmlException
    {
        return new PageXmlException("{$this->at}: <{$this->name}>: {$message}");
    }

    /**
     * Result of check, the errors it throws (without location) given this element's location
     *
     * @template T
     *
     * @param callable(): T $check
     *
     * @return T
     *
     * @throws PageXmlException
     */
    public function check(callable $check): mixed
    {
        try {
            return $check();
        } catch (PageXmlException|\InvalidArgumentException $e) {
            throw new PageXmlException("{$this->at}: <{$this->name}>: " . $e->getMessage(), 0, $e);
        }
    }
}
