<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * The --messages option of the commands reading pages: the service's messages file over the
 * framework's (as a service reads them), which a page's <message> must be one of; without it,
 * the framework's only
 */
final readonly class MessagesOption
{
    private const NAME = 'messages';

    public static function addTo(Command $command): void
    {
        $command->addOption(
            self::NAME,
            shortcut: null,
            mode: InputOption::VALUE_REQUIRED,
            description: 'The service\'s messages file (messages.xml), over the framework\'s',
        );
    }

    /**
     * @throws MessagesException if the file can't be read
     */
    public static function messages(InputInterface $input): Messages
    {
        $file = $input->getOption(self::NAME);
        $parser = new MessagesXmlParser();

        return \is_string($file) ? $parser->parseFile($file, $parser->defaults()) : $parser->defaults();
    }
}
