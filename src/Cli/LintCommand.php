<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

use MiniPaviService\Io\FileFinder;
use MiniPaviService\Io\FileReader;
use MiniPaviService\Io\Files;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Page\Building\PageXmlException;
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
    /**
     * @param FileFinder $finder finds the files of the paths given
     * @param FileReader $files  reads the files of the command line: the pages and their
     *                           Videotex files, the service's messages
     */
    public function __construct(
        private readonly FileFinder $finder = new Files(),
        private readonly FileReader $files = new Files(),
    ) {
        parent::__construct();
    }

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
            $parser = new PageXmlParser(MessagesOption::messages($input, $this->files), files: $this->files);
        } catch (MessagesException $e) {
            $errorOutput->writeln(Input::escape($e->getMessage()));

            return self::FAILURE;
        }

        $pages = 0;
        $errors = 0;
        foreach (Input::strings($input, argument: 'paths') as $path) {
            $files = $this->finder->find($path, extension: 'xml');
            if ($files === null) {
                $errorOutput->writeln(Input::escape("{$path}: not found"));
                $errors++;
                continue;
            }
            foreach ($files as $file) {
                if (!self::isPage($this->files->read($file))) {
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
     * Whether the root element of the content is <page>; content that can't be read, or isn't
     * valid XML, is taken as a page, so that the parser gives its error
     */
    private static function isPage(?string $content): bool
    {
        // Read up to the root element only: the parser reads the whole content next
        $previous = libxml_use_internal_errors(true);
        try {
            $reader = new \XMLReader();
            // Empty content can't be read, as XMLReader::XML() refuses it
            if ($content === null || $content === '' || !$reader->XML($content)) {
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
