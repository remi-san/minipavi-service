<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\Xml\XmlElements;
use MiniPaviService\Xml\XmlException;

/**
 * A <message name="...">text</message> of an XML file: of a messages file
 * (MessagesXmlParser), or of a page redefining a message for itself (PageXmlParser)
 *
 * @internal
 */
final readonly class MessageElement
{
    /**
     * @param string $at where it is, in errors: "file line n: <message>"
     */
    public function __construct(
        public string $name,
        public string $text,
        public string $at,
    ) {}

    /**
     * The <message> children of an element, in order (other children are the caller's)
     *
     * @param string $file name of the file, in errors
     *
     * @return list<self>
     *
     * @throws XmlException for a <message> without name
     */
    public static function allIn(\SimpleXMLElement $parent, string $file): array
    {
        $messages = [];
        foreach (XmlElements::children($parent) as $tag => $element) {
            if ($tag !== 'message') {
                continue;
            }
            $at = $file . ' line ' . dom_import_simplexml($element)->getLineNo() . ': <message>';
            $name = trim((string) $element['name']);
            if ($name === '') {
                throw new XmlException("{$at}: name is missing");
            }
            $messages[] = new self($name, trim((string) $element), $at);
        }

        return $messages;
    }

    /**
     * Their texts, by name
     *
     * @param list<self> $messages
     * @param string $in what they are in, in errors (e.g. "the file")
     *
     * @return array<string, string>
     *
     * @throws XmlException for a name given twice
     */
    public static function texts(array $messages, string $in): array
    {
        $texts = [];
        foreach ($messages as $message) {
            if (array_key_exists($message->name, $texts)) {
                throw new XmlException("{$message->at}: name=\"{$message->name}\" is already in {$in}");
            }
            $texts[$message->name] = $message->text;
        }

        return $texts;
    }
}
