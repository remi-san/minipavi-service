<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Text\Placeholders;
use MiniPaviService\Xml\XmlException;

/**
 * A <message name="...">text</message> of an XML file: of a messages file
 * (MessagesXmlParser), or of a page redefining a message for itself (PageMessages)
 *
 * @internal
 */
final readonly class MessageElement
{
    private function __construct(
        public string $name,
        public string $text,
        private \SimpleXMLElement $element,
        private string $file,
    ) {}

    /**
     * The message of a <message> element
     *
     * @param string $file name of the file, in errors
     *
     * @throws XmlException for one without name, or whose text has a {{...}} that isn't a
     *                      placeholder (Placeholders::check()): found when the file is read,
     *                      not when the message is shown
     */
    public static function of(\SimpleXMLElement $element, string $file): self
    {
        $name = trim((string) $element['name']);
        $message = new self($name, trim((string) $element), $element, $file);
        if ($name === '') {
            throw $message->error('name is missing');
        }
        try {
            Placeholders::check($message->text);
        } catch (InvalidValueException $e) {
            throw $message->error($e->getMessage());
        }

        return $message;
    }

    /**
     * An error of this element, giving where it is ("file line n: <message>: ...")
     */
    public function error(string $message): XmlException
    {
        $line = dom_import_simplexml($this->element)->getLineNo();

        return new XmlException("{$this->file} line {$line}: <message>: {$message}");
    }
}
