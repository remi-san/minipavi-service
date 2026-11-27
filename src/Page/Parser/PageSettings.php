<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Setup;
use MiniPaviService\Xml\XmlElements;

/**
 * The settings of a page, the attributes of its <page> element: its type, which says what it
 * may hold, and how it sets up the Minitel (keyboard, echo and scroll)
 *
 * @internal
 */
final readonly class PageSettings
{
    private function __construct(
        public PageType $type,
        public Setup $setup,
    ) {}

    /**
     * The settings of a <page> element
     *
     * @param string $name name of the page, in errors
     *
     * @throws PageXmlException for a missing type, or an attribute whose value is unknown
     */
    public static function of(\SimpleXMLElement $page, string $name): self
    {
        $type = self::setting(
            $page,
            attribute: 'type',
            cases: PageType::cases(),
            name: $name,
        ) ?? throw new PageXmlException(
            self::at($page, $name) . ': <page>: type is missing (' . self::choices('type', PageType::cases()) . ')',
        );

        return new self(
            $type,
            new Setup(
                self::setting($page, attribute: 'keyboard', cases: Keyboard::cases(), name: $name),
                self::setting($page, attribute: 'echo', cases: LocalEcho::cases(), name: $name),
                self::setting($page, attribute: 'scroll', cases: Scroll::cases(), name: $name),
            ),
        );
    }

    /**
     * Setting given by an attribute of <page>, the one of the cases of its string-backed enum
     * whose value is the attribute's; null if the attribute is missing
     *
     * @template T of \BackedEnum
     *
     * @param list<T> $cases
     *
     * @return ?T
     *
     * @throws PageXmlException for a value none of the cases has
     */
    private static function setting(
        \SimpleXMLElement $page,
        string $attribute,
        array $cases,
        string $name,
    ): ?\BackedEnum {
        $value = XmlElements::attributes($page)[$attribute] ?? null;
        if ($value === null) {
            return null;
        }
        foreach ($cases as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        throw new PageXmlException(
            self::at($page, $name)
            . ": <page>: {$attribute}=\"{$value}\" is unknown ("
            . self::choices($attribute, $cases)
            . ')',
        );
    }

    /**
     * The values an attribute of <page> can have, for its errors: attribute="a" or attribute="b"
     *
     * @param list<\BackedEnum> $cases
     */
    private static function choices(string $attribute, array $cases): string
    {
        return implode(' or ', array_map(static fn(\BackedEnum $case) => "{$attribute}=\"{$case->value}\"", $cases));
    }

    /**
     * Where <page> is, in its errors: "file line n"
     */
    private static function at(\SimpleXMLElement $page, string $name): string
    {
        return $name . ' line ' . dom_import_simplexml($page)->getLineNo();
    }
}
