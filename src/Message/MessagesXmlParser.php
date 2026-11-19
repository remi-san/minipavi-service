<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\Io\FileReader;
use MiniPaviService\Io\Files;
use MiniPaviService\Xml\XmlDocument;
use MiniPaviService\Xml\XmlElements;
use MiniPaviService\Xml\XmlException;

/**
 * Reads a messages file (root element <messages>, a <message name="...">text</message> per
 * message, format documented in the framework's README.md) over base messages, e.g. a
 * service's file over the framework's defaults. Errors give the file and line.
 */
final class MessagesXmlParser
{
    // The framework's messages
    private const DEFAULTS = __DIR__ . '/../../config/messages.xml';

    /**
     * @param FileReader $files reads the messages files, the framework's included
     */
    public function __construct(
        private readonly FileReader $files = new Files(),
    ) {}

    /**
     * The framework's messages (config/messages.xml), each a FrameworkMessage: what a
     * service's messages file is read over, read where the service is built (its container,
     * KernelBuilder's caller), once
     *
     * @throws MessagesException if the file can't be read
     */
    public function defaults(): Messages
    {
        return $this->parseFile(self::DEFAULTS, new Messages());
    }

    /**
     * @param Messages $base the messages the file redefines and adds to (new Messages() for
     *                       none)
     *
     * @throws MessagesException
     */
    public function parseFile(string $file, Messages $base): Messages
    {
        $xml = $this->files->read($file);
        if ($xml === null) {
            throw new MessagesException("{$file}: file not found");
        }

        return $this->parse($xml, $base, $file);
    }

    /**
     * @param Messages $base the messages the file redefines and adds to (new Messages() for
     *                       none)
     * @param string $name name of the file, in errors
     *
     * @throws MessagesException
     */
    public function parse(string $xml, Messages $base, string $name = 'messages'): Messages
    {
        try {
            $root = XmlDocument::withRoot($xml, $name, root: 'messages');

            return $base->with(self::texts($root, $name));
        } catch (XmlException $e) {
            throw new MessagesException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Texts of the <message> children of the root, by name
     *
     * @return array<string, string>
     *
     * @throws XmlException
     */
    private static function texts(\SimpleXMLElement $root, string $name): array
    {
        // One pass, in document order: the first error in the file is the one reported
        $texts = new MessageTexts(in: 'the file');
        foreach (XmlElements::children($root) as $tag => $element) {
            if ($tag !== 'message') {
                $line = dom_import_simplexml($element)->getLineNo();
                throw new XmlException("{$name} line {$line}: <{$tag}>: unknown element");
            }
            $texts->add(MessageElement::of($element, $name));
        }

        return $texts->texts();
    }
}
