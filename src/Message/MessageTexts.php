<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\Xml\XmlException;

/**
 * The texts of the <message> elements of a file, by name, as they are read (MessageElement)
 *
 * @internal
 */
final class MessageTexts
{
    /** @var array<string, string> */
    private array $texts = [];

    /**
     * @param string $in what they are in, in errors (e.g. "the file")
     */
    public function __construct(
        private readonly string $in,
    ) {}

    /**
     * @throws XmlException for a name already added
     */
    public function add(MessageElement $message): void
    {
        if (array_key_exists($message->name, $this->texts)) {
            throw $message->error("name=\"{$message->name}\" is already in {$this->in}");
        }
        $this->texts[$message->name] = $message->text;
    }

    /**
     * @return array<string, string> by name
     */
    public function texts(): array
    {
        return $this->texts;
    }
}
