<?php

declare(strict_types=1);

namespace MiniPaviService\Xml;

/**
 * Reads an XML file with SimpleXML, its errors as an XmlException giving the file and the
 * line of the first one, rather than PHP warnings. CDATA sections are read as text. Used by the
 * parsers of the XML files (pages, messages) and by `minipavi lint`.
 *
 * @internal
 */
final readonly class XmlDocument
{
    /**
     * The root element of the XML
     *
     * @param string $name name of the file, in errors
     *
     * @throws XmlException if it isn't well-formed
     */
    public static function load(string $xml, string $name): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $root = simplexml_load_string($xml, class_name: null, options: LIBXML_NOCDATA);
            $error = libxml_get_errors()[0] ?? null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($root === false) {
            throw new XmlException(
                $error !== null ? "{$name} line {$error->line}: " . trim($error->message) : "{$name}: invalid XML",
            );
        }

        return $root;
    }

    /**
     * The root element of the XML, which must be <root>
     *
     * @param string $name name of the file, in errors
     *
     * @throws XmlException if it isn't well-formed, or its root is another element
     */
    public static function withRoot(string $xml, string $name, string $root): \SimpleXMLElement
    {
        $element = self::load($xml, $name);
        if ($element->getName() !== $root) {
            throw new XmlException("{$name}: root element must be <{$root}>, not <" . $element->getName() . '>');
        }

        return $element;
    }
}
