<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Text\Placeholders;

/**
 * Texts of the messages shown on the Minitel, by name (Message): the framework's defaults
 * (MessagesXmlParser::defaults()), a service's messages file over them (MessagesXmlParser), a page's
 * <message> over those (with()). A text can have {{placeholders}}, replaced by get(), and
 * only those: a {{...}} that isn't one is refused when they are built.
 * Immutable.
 */
final readonly class Messages
{
    /**
     * @param array<string, string> $texts name => text
     *
     * @throws MessagesException naming the message, for a text with a {{...}} that isn't a
     *                           placeholder (e.g. {{1}}): get() would leave it in the text,
     *                           a mistake in the message, found when the messages are built
     */
    public function __construct(
        private array $texts = [],
    ) {
        foreach ($texts as $name => $text) {
            try {
                Placeholders::check($text);
            } catch (InvalidValueException $e) {
                throw new MessagesException(self::errorOf($name, $e), previous: $e);
            }
        }
    }

    /**
     * These messages, with texts redefining or adding some
     *
     * @param array<string, string> $texts name => text
     *
     * @throws MessagesException for a text with a {{...}} that isn't a placeholder
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
     * @throws MessagesException naming the message, for a message without text, or a
     *                           placeholder without variable or whose value isn't text. An
     *                           exception of a variable's own code (e.g. its __toString(),
     *                           or __get()) goes through as it is.
     */
    public function get(Message $message, array $variables = []): string
    {
        $name = $message->value;
        $text = $this->texts[$name] ?? throw new MessagesException("No text for the message \"{$name}\"");

        try {
            return (new Placeholders($variables))->substitute($text);
        } catch (InvalidValueException $e) {
            throw new MessagesException(self::errorOf($name, $e), previous: $e);
        }
    }

    /**
     * Checks there is a text for every one of these messages, e.g. at startup for every case
     * of the service's enums: check(...FrameworkMessage::cases(), ...AccMessage::cases()),
     * and that no two of them, of different enums, have the same name: they would share
     * their text, which is by name
     *
     * @throws MessagesException naming the messages of different enums with the same name,
     *                           or else the messages without text
     */
    public function check(Message ...$messages): self
    {
        $byName = [];
        $shared = [];
        foreach ($messages as $message) {
            $byName[$message->value] ??= $message;
            $first = $byName[$message->value];
            if ($first === $message) {
                continue;
            }

            $shared[] = self::nameOf($first) . ' and ' . self::nameOf($message) . " are both \"{$message->value}\"";
        }
        if ($shared !== []) {
            throw new MessagesException('Messages of different enums with the same name: ' . implode(', ', $shared));
        }

        $missing = [];
        foreach ($messages as $message) {
            if ($this->has((string) $message->value)) {
                continue;
            }

            $missing[] = $message->value;
        }
        if ($missing !== []) {
            throw new MessagesException('No text for the messages: ' . implode(', ', $missing));
        }

        return $this;
    }

    /**
     * A message as written in the code: its enum and case (e.g. FrameworkMessage::Error)
     */
    private static function nameOf(Message $message): string
    {
        return $message::class . '::' . $message->name;
    }

    /**
     * Error of a message's text, naming the message (its name, a Message's value): when the
     * messages are built, or when it is shown
     */
    private static function errorOf(int|string $name, InvalidValueException $error): string
    {
        return "Message \"{$name}\": " . $error->getMessage();
    }
}
