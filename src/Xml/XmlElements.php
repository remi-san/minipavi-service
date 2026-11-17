<?php

declare(strict_types=1);

namespace MiniPaviService\Xml;

/**
 * Elements of SimpleXML, typed: the child elements of an element, and its attributes, by
 * name. Used by the parsers of the XML files (pages, messages).
 *
 * @internal
 */
final readonly class XmlElements
{
    /**
     * Child elements, by tag (a tag may come more than once)
     *
     * @return \Generator<string, \SimpleXMLElement>
     */
    public static function children(\SimpleXMLElement $parent): \Generator
    {
        foreach ($parent->children() ?? [] as $tag => $child) {
            // Never null for an element's child, which the types of SimpleXMLElement allow
            if ($child === null) {
                continue;
            }

            yield $tag => $child;
        }
    }

    /**
     * Attributes, by name
     *
     * @return array<string, string>
     */
    public static function attributes(\SimpleXMLElement $element): array
    {
        $attributes = [];
        foreach ($element->attributes() ?? [] as $name => $value) {
            $attributes[$name] = (string) $value;
        }

        return $attributes;
    }
}
