<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Typed arguments of the commands, and text written as is (not read as the console's <tags>,
 * e.g. the <image> of an error in a page)
 *
 * @internal
 */
final readonly class Input
{
    public static function string(InputInterface $input, string $argument): string
    {
        $value = $input->getArgument($argument);

        return \is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    public static function strings(InputInterface $input, string $argument): array
    {
        $values = $input->getArgument($argument);

        return \is_array($values) ? array_values(array_filter($values, \is_string(...))) : [];
    }

    public static function escape(string $text): string
    {
        return OutputFormatter::escape($text);
    }
}
