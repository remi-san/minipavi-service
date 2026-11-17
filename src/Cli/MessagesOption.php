<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;

/**
 * The --messages option of the commands reading pages: the service's messages file over the
 * framework's (as a service reads them), which a page's <message> must be one of; without it,
 * the framework's only
 */
final readonly class MessagesOption
{
    public const NAME = 'messages';

    public const USAGE = '[--messages <messages.xml>]';

    /**
     * @throws MessagesException if the file can't be read
     */
    public static function messages(Arguments $arguments): Messages
    {
        $file = $arguments->value(self::NAME, '');

        return $file === '' ? Messages::defaults() : (new MessagesXmlParser())->parseFile($file, Messages::defaults());
    }
}
