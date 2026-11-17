<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Message\MessagesException;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Page\Template\TemplateException;

/**
 * Prints the screen a page draws, as text (Screen::preview()): a template with the data of a
 * JSON file (an object, its variables by name), for a Minitel without particular features
 * (Terminal's defaults)
 */
final readonly class PreviewCommand implements Command
{
    public function name(): string
    {
        return 'preview';
    }

    public function usage(): string
    {
        return MessagesOption::USAGE . ' <page.xml> [<data.json>]';
    }

    public function description(): string
    {
        return 'Prints the screen a page draws, a template with the data of a JSON file';
    }

    public function run(array $arguments, Console $console): ExitCode
    {
        $arguments = Arguments::parse($arguments, options: [MessagesOption::NAME]);
        $positional = $arguments->positional;
        if (\count($positional) < 1 || \count($positional) > 2) {
            throw new UsageException('expects a page, and optionally its data');
        }

        try {
            $data = isset($positional[1]) ? self::data($positional[1]) : [];
            $page = (new PageXmlParser(MessagesOption::messages($arguments)))->parseFile($positional[0]);
            $console->write($page->display(new Terminal(), $data)->screen()->preview());
        } catch (MessagesException|PageXmlException|TemplateException|\UnexpectedValueException $e) {
            $console->error($e->getMessage() . "\n");

            return ExitCode::Failure;
        }

        return ExitCode::Success;
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
        $json = @file_get_contents($file);
        if ($json === false) {
            throw new \UnexpectedValueException("$file: file not found");
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException("$file: " . $e->getMessage(), 0, $e);
        }
        if (!\is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new \UnexpectedValueException("$file: not a JSON object");
        }

        $variables = [];
        foreach ($data as $name => $value) {
            $variables[(string) $name] = $value;
        }

        return $variables;
    }
}
