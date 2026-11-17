<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Message\MessagesException;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;

/**
 * Checks pages, for CI: parses every page (XML file whose root is <page>) of the paths given,
 * files or folders (with their subfolders), as the service does, so that their errors (bad
 * XML, unknown element, missing .vdt file...) are found before the screen is shown. Other XML
 * files (e.g. messages.xml) are skipped. Fails if a page has an error, or if there is no page.
 */
final readonly class LintCommand implements Command
{
    public function name(): string
    {
        return 'lint';
    }

    public function usage(): string
    {
        return MessagesOption::USAGE . ' <path>...';
    }

    public function description(): string
    {
        return 'Checks the pages of the files and folders given, with the service\'s messages; fails on an error';
    }

    public function run(array $arguments, Console $console): ExitCode
    {
        $arguments = Arguments::parse($arguments, options: [MessagesOption::NAME]);
        if ($arguments->positional === []) {
            throw new UsageException('expects files or folders');
        }
        try {
            $parser = new PageXmlParser(MessagesOption::messages($arguments));
        } catch (MessagesException $e) {
            $console->error($e->getMessage() . "\n");

            return ExitCode::Failure;
        }

        $pages = 0;
        $errors = 0;
        foreach ($arguments->positional as $path) {
            if (!file_exists($path)) {
                $console->error("$path: not found\n");
                $errors++;
                continue;
            }
            foreach (self::xmlFiles($path) as $file) {
                if (!self::isPage($file)) {
                    continue;
                }
                $pages++;
                try {
                    $parser->parseFile($file);
                } catch (PageXmlException $e) {
                    $console->error($e->getMessage() . "\n");
                    $errors++;
                }
            }
        }

        if ($pages === 0 && $errors === 0) {
            $console->error("No page found\n");

            return ExitCode::Failure;
        }
        $console->write("$pages page" . ($pages === 1 ? '' : 's') . " checked, $errors error" . ($errors === 1 ? '' : 's') . "\n");

        return $errors === 0 ? ExitCode::Success : ExitCode::Failure;
    }

    /**
     * The file, or the .xml files of the folder and its subfolders, in order
     *
     * @return list<string>
     */
    private static function xmlFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [$path];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Whether the root element of the file is <page>; a file that isn't valid XML is taken as
     * one, so that the parser gives its error
     */
    private static function isPage(string $file): bool
    {
        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_file($file);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $root === false || $root->getName() === 'page';
    }
}
