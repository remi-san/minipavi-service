<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Building\Placement;
use MiniPaviService\Page\Display\Background;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Text;
use MiniPaviService\Page\Parser\PageElements;
use MiniPaviService\Page\Parser\PageFiles;
use MiniPaviService\Vdt\Vdt;

/**
 * Display elements of a service: <stars count="n" row="r"/>, a row of n stars (its row
 * relative to the item in a <foreach>), <picture>file</picture>, a Videotex file of the page's
 * folder (at its own place), <signature/>, a text at the bottom of the page, once per page (not
 * in a <foreach>), and <broken/>, whose builder fails; or, given a tag, one element of that tag
 */
final readonly class StarsElements implements PageElements
{
    public function __construct(
        private ?string $tag = null,
    ) {}

    public function builders(PageFiles $files, Messages $messages): array
    {
        if ($this->tag !== null) {
            return [self::builder($this->tag, static fn() => new Background(new Vdt()))];
        }

        // Keyed by tag, which the parser ignores
        return [
            'stars' => self::builder(
                'stars',
                static fn(AttributeValues $attributes, string $text, Placement $placement) => new Text(
                    str_repeat('*', max(0, $attributes->int('count', default: 1))),
                    new Position($placement->placeByRow($attributes)->int('row', default: 1)),
                    centered: false,
                    color: Color::White,
                    bg: null,
                    size: null,
                    fill: false,
                ),
            ),
            'picture' => self::builder(
                'picture',
                static fn(AttributeValues $attributes, string $text) => new Background(new Vdt($files->read($text))),
            ),
            'signature' => self::builder(
                'signature',
                static fn() => new Text(
                    'Signed',
                    new Position(24),
                    centered: false,
                    color: Color::White,
                    bg: null,
                    size: null,
                    fill: false,
                ),
                static fn(Placement $placement) => $placement->refuseInLoop(),
            ),
            // A bug in the service's code, not in the page
            'broken' => self::builder(
                'broken',
                static fn() => throw new \InvalidArgumentException('a bug building <broken>'),
            ),
        ];
    }

    /**
     * @param \Closure(AttributeValues, string, Placement): Element $build
     * @param ?\Closure(Placement): void $check checks where it is, when the page is read
     */
    private static function builder(string $tag, \Closure $build, ?\Closure $check = null): ElementBuilder
    {
        return new class($tag, $build, $check) implements ElementBuilder {
            /**
             * @param \Closure(AttributeValues, string, Placement): Element $build
             * @param ?\Closure(Placement): void $check
             */
            public function __construct(
                private readonly string $tag,
                private readonly \Closure $build,
                private readonly ?\Closure $check,
            ) {}

            public function tag(): string
            {
                return $this->tag;
            }

            public function checkPlacement(Placement $placement): void
            {
                if ($this->check !== null) {
                    ($this->check)($placement);
                }
            }

            public function build(AttributeValues $attributes, string $text, Placement $placement): Element
            {
                return ($this->build)($attributes, $text, $placement);
            }
        };
    }
}
