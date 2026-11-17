<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

/**
 * Variables of $_SERVER (headers, host, URI...), as strings
 *
 * @internal
 */
final class ServerVariable
{
    /**
     * Value of the variable, or '' if it is not set (or not a string)
     */
    public static function get(string $name): string
    {
        $value = $_SERVER[$name] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * Whether a flag such as HTTPS is on: set, and other than '0' or 'off'
     */
    /**
     * Value of a parameter of the query string (QUERY_STRING), null if missing or not text
     */
    public static function query(string $name): ?string
    {
        parse_str(self::get('QUERY_STRING'), $parameters);
        $value = $parameters[$name] ?? null;

        return \is_string($value) ? $value : null;
    }

    public static function isOn(string $name): bool
    {
        return !\in_array(strtolower(self::get($name)), ['', '0', 'off'], true);
    }
}
