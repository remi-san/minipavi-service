<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;

/**
 * An input command as JSON, both ways: how HtmlForm posts it back with the page, to know, when
 * the page is posted, which fields the user filled in
 */
final readonly class InputCommandJson
{
    /**
     * The command from its JSON (encode()), null if there is none or it can't be read: what
     * the browser posts back may be anything
     */
    public function decode(string $json): ?InputCommand
    {
        $command = json_decode($json, associative: true);
        if (!\is_array($command)) {
            return null;
        }
        foreach (['row', 'col', 'length', 'lines'] as $name) {
            if (!\is_int($command[$name] ?? null)) {
                return null;
            }
        }
        $validWith = [];
        foreach (\is_array($command['validWith'] ?? null) ? $command['validWith'] : [] as $name) {
            $key = \is_string($name) ? FunctionKey::tryFrom($name) : null;
            if ($key === null) {
                continue;
            }
            $validWith[] = $key;
        }
        $prefill = array_values(array_map(
            static fn(mixed $line) => \is_string($line) ? $line : '',
            \is_array($command['prefill'] ?? null) ? $command['prefill'] : [],
        ));
        // What is missing (or can't be read, e.g. no key it knows) has the field's defaults:
        // validated with Send, spaces shown as dots
        $given = ['cursor' => ($command['cursor'] ?? true) === true];
        if ($validWith !== []) {
            $given['validWith'] = $validWith;
        }
        if (\is_string($command['spaceChar'] ?? null)) {
            $given['spaceChar'] = $command['spaceChar'];
        }
        try {
            $field = new InputField(
                new Span(
                    new Position(
                        self::int($command, name: 'row', default: 1),
                        self::int($command, name: 'col', default: 1),
                    ),
                    self::int($command, name: 'length', default: 1),
                ),
                self::int($command, name: 'lines', default: 1),
                ...$given,
            );
        } catch (\InvalidArgumentException) {
            // Off the screen
            return null;
        }

        return new InputCommand($field, $prefill);
    }

    /**
     * The command as JSON, read back by decode()
     *
     * @throws \JsonException if it can't be written
     */
    public function encode(InputCommand $command): string
    {
        $field = $command->field;

        // Text that isn't UTF-8 (e.g. a prefill from a service that isn't) is replaced (U+FFFD)
        // rather than losing the whole command; any other failure throws
        return json_encode([
            'row' => $field->span->start->row,
            'col' => $field->span->start->col,
            'length' => $field->span->length,
            'lines' => $field->lines,
            'validWith' => array_map(static fn(FunctionKey $key) => $key->value, $field->validWith),
            'cursor' => $field->cursor,
            'spaceChar' => $field->spaceChar,
            'prefill' => $command->prefill,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param array<mixed> $values
     */
    private static function int(array $values, string $name, int $default): int
    {
        $value = $values[$name] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
