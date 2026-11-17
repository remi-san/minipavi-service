<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

/**
 * Reads a messages file (root element <messages>, a <message name="...">text</message> per
 * message, format documented in the framework's README.md) over base messages, e.g. a
 * service's file over the framework's defaults. Errors give the file and line.
 */
final class MessagesXmlParser
{
    /**
     * @param Messages $base the messages the file redefines and adds to (new Messages() for
     *                       none)
     *
     * @throws MessagesException
     */
    public function parseFile(string $file, Messages $base): Messages
    {
        $xml = is_file($file) ? file_get_contents($file) : false;
        if ($xml === false) {
            throw new MessagesException("$file: file not found");
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
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $root = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($root === false) {
            $error = $errors[0] ?? null;
            throw new MessagesException(
                $error !== null ? "$name line $error->line: " . trim($error->message) : "$name: invalid XML",
            );
        }
        if ($root->getName() !== 'messages') {
            throw new MessagesException("$name: root element must be <messages>, not <" . $root->getName() . '>');
        }

        return $base->with(self::texts($root, $name));
    }

    /**
     * Texts of the <message> children of the root, by name
     *
     * @return array<string, string>
     *
     * @throws MessagesException
     */
    private static function texts(\SimpleXMLElement $parent, string $name): array
    {
        $texts = [];
        foreach ($parent->children() as $tag => $element) {
            $at = $name . ' line ' . dom_import_simplexml($element)->getLineNo();
            if ($tag !== 'message') {
                throw new MessagesException("$at: <$tag>: unknown element");
            }
            $at .= ': <message>';
            $messageName = trim((string) $element['name']);
            if ($messageName === '') {
                throw new MessagesException("$at: name is missing");
            }
            if (isset($texts[$messageName])) {
                throw new MessagesException("$at: name=\"$messageName\" is already in the file");
            }
            $texts[$messageName] = trim((string) $element);
        }

        return $texts;
    }
}
