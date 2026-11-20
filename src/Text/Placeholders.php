<?php

declare(strict_types=1);

namespace MiniPaviService\Text;

use MiniPaviService\InvalidValueException;

/**
 * The {{placeholders}} of a text, and the values of variables they name: {{name}}, or a dotted
 * path through arrays and public object properties ({{game.studio.city}}). Used by page templates
 * (Page\Template\Scope) and messages (Message\Messages).
 */
final readonly class Placeholders
{
    // {{name}} or {{name.field.subfield}}, spaces allowed inside the braces
    public const PLACEHOLDER = '/\{\{\s*([A-Za-z_]\w*(?:\.\w+)*)\s*\}\}/';

    /**
     * @param array<string, mixed> $vars name => value (scalar, array, object with public
     *                                   properties, iterable)
     */
    public function __construct(
        private array $vars = [],
    ) {}

    /**
     * The first {{...}} of a text that isn't a placeholder (e.g. {{1}}, {{user-name}}, or
     * {{user.}}), which substitute() leaves as it is; null if there is none
     */
    public static function malformed(string $text): ?string
    {
        $candidates = [];
        preg_match_all('/\{\{.*?\}\}/s', $text, $candidates);
        foreach ($candidates[0] as $candidate) {
            $match = [];
            if (preg_match(self::PLACEHOLDER, $candidate, $match) !== 1 || $match[0] !== $candidate) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Text with its {{placeholders}} replaced by the values of the variables
     */
    public function substitute(string $text): string
    {
        $result = preg_replace_callback(
            self::PLACEHOLDER,
            function ($m) {
                $value = $this->value($m[1]);
                // As PHP writes them: true is '1', false and null ''
                if ($value === null || is_scalar($value) || $value instanceof \Stringable) {
                    return (string) $value;
                }
                throw new InvalidValueException("{{{$m[1]}}} is not text (" . get_debug_type($value) . ')');
            },
            $text,
        );
        if ($result === null) {
            throw new InvalidValueException('cannot replace the placeholders: ' . preg_last_error_msg());
        }

        return $result;
    }

    /**
     * Value of a variable, following dots through arrays and object properties
     */
    public function value(string $path): mixed
    {
        $names = explode('.', $path);
        $first = array_shift($names);
        if (!array_key_exists($first, $this->vars)) {
            throw new InvalidValueException("unknown variable {{{$path}}}");
        }
        $value = $this->vars[$first];
        foreach ($names as $name) {
            $value = self::member($value, $name, $path);
        }
        return $value;
    }

    /**
     * Element of an array (or ArrayAccess), or public property of an object, by name
     *
     * @throws InvalidValueException if it hasn't any of that name
     */
    private static function member(mixed $value, string $name, string $path): mixed
    {
        if (is_array($value) && array_key_exists($name, $value)) {
            return $value[$name];
        }
        if ($value instanceof \ArrayAccess && $value->offsetExists($name)) {
            return $value[$name];
        }
        // Its public property (get_object_vars() called from outside sees only those, unlike
        // property_exists()), or one it gives through __isset() and __get()
        if (
            is_object($value)
            && (
                array_key_exists($name, get_object_vars($value))
                || method_exists($value, '__isset') && $value->__isset($name)
            )
        ) {
            return $value->$name;
        }
        throw new InvalidValueException("unknown variable {{{$path}}}: no \"{$name}\" in " . get_debug_type($value));
    }
}
