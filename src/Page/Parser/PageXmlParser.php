<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\{Keyboard, LocalEcho, Scroll};
use MiniPaviService\Page;
use MiniPaviService\Page\Display\Display;
use MiniPaviService\Page\{Input, Setup, StaticPage, TemplatePage};
use MiniPaviService\Page\Template\{ElementNode, ForeachNode, IfNode, Node, Scope, TemplateException};

/**
 * Reads a page XML file (root element <page>, format documented in the framework's README.md)
 * into a Page, whose type is given by the type attribute of <page>: a StaticPage for
 * type="static", a TemplatePage, with {{placeholders}} and instructions (<foreach>,
 * <if>), for type="template". Anything unknown or invalid throws a PageXmlException
 * giving the file and line.
 */
final class PageXmlParser
{
    private const TYPES = ['static','template'];

    /**
     * @param Messages $messages the service's messages (e.g. Messages::defaults() for the
     *                           framework's only), which a page's <message> redefine
     */
    public function __construct(
        private Messages $messages,
    ) {}

    /**
     * Page from a file; <background> and <image> files are read from the same folder
     */
    public function parseFile(string $file): Page
    {
        $xml = @file_get_contents($file);
        if ($xml === false) {
            throw new PageXmlException("$file: file not found");
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
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $root = simplexml_load_string($xml, null, LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($root === false) {
            $error = $errors[0] ?? null;
            throw new PageXmlException($error ? "$name line $error->line: " . trim($error->message) : "$name: invalid XML");
        }
        if ($root->getName() != 'page') {
            throw new PageXmlException("$name: root element must be <page>, not <" . $root->getName() . '>');
        }
        $type = trim((string) $root['type']);
        if (!in_array($type, self::TYPES)) {
            throw new PageXmlException("$name: <page>: " . ($type === '' ? 'type is missing' : "type=\"$type\" is unknown") . ' (type="' . implode('" or type="', self::TYPES) . '")');
        }

        $setup = new Setup(
            self::setting($root, 'keyboard', Keyboard::class, $name),
            self::setting($root, 'echo', LocalEcho::class, $name),
            self::setting($root, 'scroll', Scroll::class, $name),
        );

        $messages = $this->messages->with($this->messageTexts($root, $name));
        $factory = new ElementFactory($baseDir, $messages);
        $input = null;
        $nodes = $this->nodes($root, $factory, $name, $type == 'template', false, $input);

        if ($type == 'template') {
            return new TemplatePage($nodes, $messages, $input, $setup);
        }

        // Static page: elements are built now, so that their errors are found now
        try {
            $elements = [];
            foreach ($nodes as $node) {
                array_push($elements, ...$node->elements(new Scope()));
            }
        } catch (TemplateException $e) {
            throw new PageXmlException($e->getMessage(), 0, $e);
        }
        return new StaticPage(new Display(...$elements), $messages, $input, $setup);
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
    private static function setting(\SimpleXMLElement $root, string $attribute, string $enum, string $name): ?\BackedEnum
    {
        if (!isset($root[$attribute])) {
            return null;
        }
        $value = (string) $root[$attribute];

        return $enum::tryFrom($value) ?? throw new PageXmlException(\sprintf(
            '%s line %d: <page>: %s="%s" is unknown (%s)',
            $name,
            dom_import_simplexml($root)->getLineNo(),
            $attribute,
            $value,
            implode(' or ', array_map(fn(\BackedEnum $case) => "$attribute=\"$case->value\"", $enum::cases())),
        ));
    }

    /**
     * Nodes for the children of an XML element.
     *
     * @param bool $template page of type="template", the only one with placeholders and instructions
     * @param bool $inLoop inside a <foreach>
     * @param ?Input $input set to the <input> of the page, found at its top level only
     *
     * @return Node[]
     */
    private function nodes(\SimpleXMLElement $parent, ElementFactory $factory, string $name, bool $template, bool $inLoop, ?Input &$input): array
    {
        $nodes = [];
        foreach ($parent->children() as $tag => $elem) {
            $at = $name . ' line ' . dom_import_simplexml($elem)->getLineNo();
            $attributes = [];
            foreach ($elem->attributes() as $attr => $value) {
                $attributes[$attr] = (string) $value;
            }
            $hasPlaceholder = (bool) preg_match(Scope::PLACEHOLDER, implode("\n", $attributes) . "\n" . (string) $elem);

            switch ($tag) {
                case 'message':
                    // Read by messageTexts()
                    if ($parent->getName() != 'page') {
                        throw new PageXmlException("$at: <message>: only at the top level of the page");
                    }
                    break;

                case 'input':
                    $input = $this->check($at, $tag, function () use ($parent, $input, $hasPlaceholder, $factory, $attributes) {
                        if ($parent->getName() != 'page') {
                            throw new PageXmlException('only at the top level of the page');
                        }
                        if ($input !== null) {
                            throw new PageXmlException('only one <input> per page');
                        }
                        if ($hasPlaceholder) {
                            throw new PageXmlException('no {{placeholders}} in <input>');
                        }
                        return $factory->input($attributes);
                    });
                    break;

                case 'foreach':
                    [$row,$step] = $this->check($at, $tag, function () use ($attributes, $factory, $inLoop, $template) {
                        if (!$template) {
                            throw new PageXmlException('only in a page of type="template"');
                        }
                        foreach (['items','as'] as $required) {
                            if (trim($attributes[$required] ?? '') === '') {
                                throw new PageXmlException("$required is missing");
                            }
                        }
                        if (!preg_match('/^[A-Za-z_]\w*$/', $attributes['as']) || $attributes['as'] == 'loop') {
                            throw new PageXmlException("as=\"{$attributes['as']}\" must be a variable name, other than loop");
                        }
                        // Absolute outside of any <foreach>, relative to the enclosing item inside
                        return [$factory->int($attributes, 'row', $inLoop ? 0 : 1),$factory->int($attributes, 'step', 1)];
                    });
                    $children = $this->nodes($elem, $factory, $name, $template, true, $input);
                    $nodes[] = new ForeachNode(trim($attributes['items']), $attributes['as'], $row, $step, $children, $at);
                    break;

                case 'if':
                    $test = trim($attributes['test'] ?? '');
                    $this->check($at, $tag, function () use ($test, $template) {
                        if (!$template) {
                            throw new PageXmlException('only in a page of type="template"');
                        }
                        if ($test === '' || $test === '!') {
                            throw new PageXmlException('test is missing');
                        }
                    });
                    $children = $this->nodes($elem, $factory, $name, $template, $inLoop, $input);
                    $nodes[] = new IfNode(ltrim($test, '!'), $test[0] == '!', $children, $at);
                    break;

                default:
                    $this->check($at, $tag, function () use ($tag, $inLoop, $template, $hasPlaceholder) {
                        if (!in_array($tag, ElementFactory::TAGS)) {
                            throw new PageXmlException('unknown element');
                        }
                        if ($tag == 'endkey' && $inLoop) {
                            throw new PageXmlException('not inside <foreach>');
                        }
                        if ($hasPlaceholder && !$template) {
                            throw new PageXmlException('{{placeholders}} only in a page of type="template"');
                        }
                    });
                    $nodes[] = new ElementNode($factory, $tag, $attributes, (string) $elem, $at);
            }
        }
        return $nodes;
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
        $texts = [];
        foreach ($root->children() as $tag => $element) {
            if ($tag !== 'message') {
                continue;
            }
            $at = $name . ' line ' . dom_import_simplexml($element)->getLineNo() . ': <message>';
            $messageName = trim((string) $element['name']);
            if ($messageName === '') {
                throw new PageXmlException("$at: name is missing");
            }
            if (!$this->messages->has($messageName)) {
                throw new PageXmlException("$at: name=\"$messageName\" is not a message of the service");
            }
            if (isset($texts[$messageName])) {
                throw new PageXmlException("$at: name=\"$messageName\" is already in the page");
            }
            $texts[$messageName] = trim((string) $element);
        }

        return $texts;
    }

    /**
     * Result of check(), with the location added to its error message
     *
     * @template T
     * @param callable(): T $check
     * @return T
     */
    private function check(string $at, string $tag, callable $check): mixed
    {
        try {
            return $check();
        } catch (PageXmlException|\InvalidArgumentException $e) {
            throw new PageXmlException("$at: <$tag>: " . $e->getMessage(), 0, $e);
        }
    }
}
