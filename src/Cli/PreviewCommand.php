<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Io\Files;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Vdt\Screen\Screen;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints the screen a page draws, as text (Screen::preview()): a template with the data of a
 * JSON file (an object, its variables by name), for a Minitel without particular features
 * (Terminal's defaults)
 */
#[AsCommand(name: 'preview', description: 'Prints the screen a page draws, a template with the data of a JSON file')]
final class PreviewCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('page', InputArgument::REQUIRED, description: 'The page file')->addArgument(
            'data',
            InputArgument::OPTIONAL,
            description: 'The variables of a template: a JSON file, with an object',
        );
        MessagesOption::addTo($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $data = $input->getArgument('data');
            $variables = \is_string($data) ? self::data($data) : [];
            $page = (new PageXmlParser(MessagesOption::messages($input)))->parseFile(Input::string(
                $input,
                argument: 'page',
            ));
            $output->write(
                Screen::of($page->display(new Terminal(), $variables))->preview(),
                newline: false,
                options: OutputInterface::OUTPUT_RAW,
            );
        } catch (MessagesException|PageXmlException|TemplateException|\UnexpectedValueException $e) {
            (new SymfonyStyle($input, $output))
                ->getErrorStyle()
                ->writeln(Input::escape($e->getMessage()));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Variables of a template, from a JSON file
     *
     * @return array<string, mixed>
     *
     * @throws \UnexpectedValueException if it can't be read, or isn't a JSON object
     */
    private static function data(string $file): array
    {
        $json = (new Files())->read($file);
        if ($json === null) {
            throw new \UnexpectedValueException("{$file}: file not found");
        }
        try {
            $data = json_decode($json, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException("{$file}: " . $e->getMessage(), previous: $e);
        }
        if (!\is_array($data) || $data !== [] && array_is_list($data)) {
            throw new \UnexpectedValueException("{$file}: not a JSON object");
        }

        $variables = [];
        foreach ($data as $name => $value) {
            $variables[(string) $name] = $value;
        }

        return $variables;
    }
}
