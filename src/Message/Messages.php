<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\Text\Placeholders;

/**
 * Texts of the messages shown on the Minitel, by name (Message): the framework's defaults
 * (MessagesXmlParser::defaults()), a service's messages file over them (MessagesXmlParser), a page's
 * <message> over those (with()). A text can have {{placeholders}}, replaced by get().
 * Immutable.
 */
final readonly class Messages
{
    /**
     * @param array<string, string> $texts name => text
     */
    public function __construct(
        private array $texts = [],
    ) {}

    /**
     * These messages, with texts redefining or adding some
     *
     * @param array<string, string> $texts name => text
     */
    public function with(array $texts): self
    {
        return new self($texts + $this->texts);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->texts);
    }

    /**
     * Text of a message, its {{placeholders}} replaced by variables, as in page templates
     * (Text\Placeholders): {{name}}, or a dotted path through arrays and object properties
     *
     * @param array<string, mixed> $variables name => value: text, number, array or object with
     *                                    properties
     *
     * @throws \LogicException naming the message, for a message without text, a placeholder
     *                         without variable or whose value isn't text, or a {{...}} that
     *                         isn't a placeholder (e.g. {{1}}). An exception of a variable's own
     *                         code (e.g. its __toString(), or __get()) goes through as it is.
     */
    public function get(Message $message, array $variables = []): string
    {
        $name = (string) $message->value;
        $text = $this->texts[$name] ?? throw new \LogicException("No text for the message \"{$name}\"");

        try {
            // Unlike in a page, it would be left in the text: a mistake in the message
            $malformed = Placeholders::malformed($text);
            if ($malformed !== null) {
                throw new \InvalidArgumentException("{$malformed} is not a placeholder");
            }

            return (new Placeholders($variables))->substitute($text);
        } catch (\InvalidArgumentException $e) {
            throw new \LogicException("Message \"{$name}\": " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Checks there is a text for every message of these enums, e.g. at startup
     *
     * @param class-string<Message> ...$enums
     *
     * @throws MessagesException naming the messages without text
     */
    public function check(string ...$enums): self
    {
        $missing = [];
        foreach ($enums as $enum) {
            foreach ($enum::cases() as $message) {
                if ($this->has((string) $message->value)) {
                    continue;
                }

                $missing[] = (string) $message->value;
            }
        }
        if ($missing !== []) {
            throw new MessagesException('No text for the messages: ' . implode(', ', $missing));
        }

        return $this;
    }
}
