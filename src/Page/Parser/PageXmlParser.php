<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Io\Files;
use MiniPaviService\Message\MessageElement;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Message\MessageTexts;
use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Page;
use MiniPaviService\Page\MinitelPage;
use MiniPaviService\Page\Parser\Tag\NodesParser;
use MiniPaviService\Page\Parser\Tag\TagPosition;
use MiniPaviService\Page\Setup;
use MiniPaviService\Page\StaticContent;
use MiniPaviService\Page\Template\Nodes;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Page\TemplateContent;
use MiniPaviService\Xml\XmlDocument;
use MiniPaviService\Xml\XmlElements;
use MiniPaviService\Xml\XmlException;

/**
 * Reads a page XML file (root element <page>, format documented in the framework's README.md)
 * into a MinitelPage, whose content the type attribute of <page> gives: StaticContent for
 * type="static", TemplateContent, with {{placeholders}} and instructions (<foreach>,
 * <if>), for type="template". Anything unknown or invalid throws a PageXmlException
 * giving the file and line.
 */
final class PageXmlParser
{
    private const TYPES = ['static', 'template'];

    /**
     * @param Messages $messages the service's messages (e.g. MessagesXmlParser::defaults() for the
     *                           framework's only), which a page's <message> redefine
     * @param PageElements $elements display elements of the service, besides the framework's
     *                               (StandardElements); their tags must be new ones
     *
     * @throws \LogicException for an element whose tag is taken, by another element or by the
     *                         format (e.g. <if>): checked now, at boot, rather than when a page
     *                         is first parsed
     */
    public function __construct(
        private Messages $messages,
        private PageElements $elements = new NoElements(),
    ) {
        NodesParser::standard($this->elementsOf(new PageFiles('.'), $messages), file: 'PageXmlParser');
    }

    /**
     * Page from a file; <background> and <image> files are read from the same folder
     */
    public function parseFile(string $file): Page
    {
        $xml = Files::read($file);
        if ($xml === null) {
            throw new PageXmlException("{$file}: file not found");
        }
        return $this->parse($xml, dirname($file), basename($file));
    }

    /**
     * Page from XML content.
     *
     * @param string $baseDir folder of the <background> and <image> files
     * @param string $name shown in error messages
     */
    public function parse(string $xml, string $baseDir, string $name = 'page'): Page
    {
        try {
            $root = XmlDocument::withRoot($xml, $name, root: 'page');
        } catch (XmlException $e) {
            throw new PageXmlException($e->getMessage(), 0, $e);
        }
        $type = trim((string) $root['type']);
        if (!in_array($type, self::TYPES, strict: true)) {
            throw new PageXmlException(
                "{$name}: <page>: "
                . ($type === '' ? 'type is missing' : "type=\"{$type}\" is unknown")
                . ' (type="'
                . implode('" or type="', self::TYPES)
                . '")',
            );
        }

        $setup = new Setup(
            self::setting($root, attribute: 'keyboard', enum: Keyboard::class, name: $name),
            self::setting($root, attribute: 'echo', enum: LocalEcho::class, name: $name),
            self::setting($root, attribute: 'scroll', enum: Scroll::class, name: $name),
        );

        $messages = $this->messages->with($this->messageTexts($root, $name));
        $parsed = NodesParser::standard($this->elementsOf(new PageFiles($baseDir), $messages), $name)->parse(
            $root,
            TagPosition::page(template: $type === 'template'),
        );
        $template = new TemplateContent(new Nodes($parsed->nodes));
        if ($type === 'template') {
            return new MinitelPage($template, $messages, $parsed->input, $setup);
        }

        // Static page: elements are built now, so that their errors are found now
        try {
            $display = $template->display([]);
        } catch (TemplateException $e) {
            throw new PageXmlException($e->getMessage(), 0, $e);
        }

        return new MinitelPage(new StaticContent($display), $messages, $parsed->input, $setup);
    }

    /**
     * The display elements of a page: the framework's, and the service's
     */
    private function elementsOf(PageFiles $files, Messages $messages): ElementFactory
    {
        return ElementFactory::of($files, $messages, new StandardElements(), $this->elements);
    }

    /**
     * Setting of the Minitel given by an attribute of <page>, a case of its enum; null if the
     * attribute is missing
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return ?T
     *
     * @throws PageXmlException for a value the enum doesn't have
     */
    private static function setting(
        \SimpleXMLElement $root,
        string $attribute,
        string $enum,
        string $name,
    ): ?\BackedEnum {
        $value = XmlElements::attributes($root)[$attribute] ?? null;
        if ($value === null) {
            return null;
        }

        return (
            $enum::tryFrom($value) ?? throw new PageXmlException(\sprintf(
                '%s line %d: <page>: %s="%s" is unknown (%s)',
                $name,
                dom_import_simplexml($root)->getLineNo(),
                $attribute,
                $value,
                implode(' or ', array_map(
                    static fn(\BackedEnum $case) => "{$attribute}=\"{$case->value}\"",
                    $enum::cases(),
                )),
            ))
        );
    }

    /**
     * Texts of the messages the page redefines (<message name> at its top level), by name:
     * messages of the service only
     *
     * @return array<string, string>
     *
     * @throws PageXmlException
     */
    private function messageTexts(\SimpleXMLElement $root, string $name): array
    {
        // One pass, in document order: the first error in the file is the one reported
        $texts = new MessageTexts(in: 'the page');
        try {
            foreach (XmlElements::children($root) as $tag => $element) {
                if ($tag !== 'message') {
                    continue;
                }
                $message = MessageElement::of($element, $name);
                if (!$this->messages->has($message->name)) {
                    throw $message->error("name=\"{$message->name}\" is not a message of the service");
                }
                $texts->add($message);
            }
        } catch (XmlException $e) {
            throw new PageXmlException($e->getMessage(), 0, $e);
        }

        return $texts->texts();
    }
}
