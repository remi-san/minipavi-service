<?php

declare(strict_types=1);

namespace MiniPaviService;

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
    public static function isOn(string $name): bool
    {
        return !\in_array(strtolower(self::get($name)), ['', '0', 'off'], true);
    }
}
