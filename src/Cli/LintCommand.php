<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Message\MessagesException;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks pages, for CI: parses every page (XML file whose root is <page>) of the paths given,
 * files or folders (with their subfolders), as the service does, so that their errors (bad
 * XML, unknown element, missing .vdt file...) are found before the screen is shown. Other XML
 * files (e.g. messages.xml) are skipped. Fails if a page has an error, or if there is no page.
 */
#[AsCommand(name: 'lint', description: 'Checks the pages of the files and folders given; fails on an error')]
final class LintCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'paths',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            description: 'Page files, or folders of pages (with their subfolders)',
        );
        MessagesOption::addTo($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = (new SymfonyStyle($input, $output))->getErrorStyle();
        try {
            $parser = new PageXmlParser(MessagesOption::messages($input));
        } catch (MessagesException $e) {
            $errorOutput->writeln(Input::escape($e->getMessage()));

            return self::FAILURE;
        }

        $pages = 0;
        $errors = 0;
        foreach (Input::strings($input, argument: 'paths') as $path) {
            if (!file_exists($path)) {
                $errorOutput->writeln(Input::escape("{$path}: not found"));
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
                    $errorOutput->writeln(Input::escape($e->getMessage()));
                    $errors++;
                }
            }
        }

        if ($pages === 0 && $errors === 0) {
            $errorOutput->writeln('No page found');

            return self::FAILURE;
        }
        $output->writeln(
            "{$pages} page" . ($pages === 1 ? '' : 's') . " checked, {$errors} error" . ($errors === 1 ? '' : 's'),
        );

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
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
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $path,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if (!($file instanceof \SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'xml')) {
                continue;
            }

            $files[] = $file->getPathname();
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
        // Read up to the root element only: the parser reads the whole file next
        $previous = libxml_use_internal_errors(true);
        try {
            $reader = new \XMLReader();
            if (!$reader->open($file)) {
                return true;
            }
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT) {
                    return $reader->name === 'page';
                }
            }

            return true;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
