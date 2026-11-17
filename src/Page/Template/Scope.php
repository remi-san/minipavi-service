<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

/**
 * Variables visible while displaying a part of a template, and where that part is
 * drawn: inside a <foreach>, rows are relative to the first row of the current item.
 */
final readonly class Scope
{
    // {{name}} or {{name.field.subfield}}, spaces allowed inside the braces
    public const PLACEHOLDER = '/\{\{\s*([A-Za-z_]\w*(?:\.\w+)*)\s*\}\}/';

    /**
     * @param array<string, mixed> $vars name => value (scalar, array, object with public properties, iterable)
     * @param ?int $rowOffset row where relative rows start, or null outside of any <foreach>
     */
    public function __construct(
        public array $vars = [],
        public ?int $rowOffset = null,
    ) {}

    /**
     * Scope with more variables, drawn from another row
     *
     * @param array<string, mixed> $vars
     */
    public function with(array $vars, int $rowOffset): self
    {
        return new self(array_merge($this->vars, $vars), $rowOffset);
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
                if (is_bool($value)) {
                    return $value ? '1' : '';
                }
                if ($value === null || is_scalar($value) || $value instanceof \Stringable) {
                    return (string) $value;
                }
                throw new \InvalidArgumentException("{{{$m[1]}}} is not text (" . get_debug_type($value) . ')');
            },
            $text,
        );
        if ($result === null) {
            throw new \InvalidArgumentException('cannot replace the placeholders: ' . preg_last_error_msg());
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
            throw new \InvalidArgumentException("unknown variable {{{$path}}}");
        }
        $value = $this->vars[$first];
        foreach ($names as $name) {
            if (is_array($value) && array_key_exists($name, $value)) {
                $value = $value[$name];
            } elseif ($value instanceof \ArrayAccess && $value->offsetExists($name)) {
                $value = $value[$name];
            } elseif (is_object($value) && (isset($value->$name) || property_exists($value, $name))) {
                $value = $value->$name;
            } else {
                throw new \InvalidArgumentException(
                    "unknown variable {{{$path}}}: no \"$name\" in " . get_debug_type($value),
                );
            }
        }
        return $value;
    }
}
