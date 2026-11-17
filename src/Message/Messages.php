<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

/**
 * Texts of the messages shown on the Minitel, by name (Message): the framework's defaults
 * (defaults()), a service's messages file over them (MessagesXmlParser), a page's
 * <message> over those (with()). A text can have {{placeholders}}, replaced by get().
 * Immutable.
 */
final readonly class Messages
{
    private const DEFAULTS = __DIR__ . '/../../config/messages.xml';

    /**
     * @param array<string, string> $texts name => text
     */
    public function __construct(
        private array $texts = [],
    ) {}

    /**
     * The framework's messages (config/messages.xml), read once
     */
    public static function defaults(): self
    {
        static $defaults = null;
        if (!$defaults instanceof self) {
            $defaults = (new MessagesXmlParser())->parseFile(self::DEFAULTS, new self());
        }

        return $defaults;
    }

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
        return isset($this->texts[$name]);
    }

    /**
     * Text of a message, its {{placeholders}} replaced by variables
     *
     * @param array<string, string|int> $variables name => value
     *
     * @throws \LogicException for a message without text, or a placeholder without variable
     */
    public function get(Message $message, array $variables = []): string
    {
        $name = (string) $message->value;
        $text = $this->texts[$name] ?? throw new \LogicException("No text for the message \"$name\"");

        return (string) preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function (array $match) use ($name, $variables): string {
            if (!\array_key_exists($match[1], $variables)) {
                throw new \LogicException("Message \"$name\" needs the variable \"$match[1]\"");
            }

            return (string) $variables[$match[1]];
        }, $text);
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
                if (!$this->has((string) $message->value)) {
                    $missing[] = (string) $message->value;
                }
            }
        }
        if ($missing !== []) {
            throw new MessagesException('No text for the messages: ' . implode(', ', $missing));
        }

        return $this;
    }
}
