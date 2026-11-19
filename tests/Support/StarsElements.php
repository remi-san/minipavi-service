<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Page\Display\Background;
use MiniPaviService\Page\Display\Element;
use MiniPaviService\Page\Display\Text;
use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Page\Parser\Element\ElementBuilder;
use MiniPaviService\Page\Parser\PageElements;
use MiniPaviService\Page\Parser\PageFiles;
use MiniPaviService\Vdt\Vdt;

/**
 * Display elements of a service: <stars count="n" row="r"/>, a row of n stars,
 * <picture>file</picture>, a Videotex file of the page's folder, and <broken/>, whose builder
 * fails; or, given a tag, one element of that tag
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
                static fn(AttributeValues $attributes) => new Text(
                    str_repeat('*', max(0, $attributes->int('count', default: 1))),
                    new Position($attributes->int('row', default: 1)),
                ),
            ),
            'picture' => self::builder(
                'picture',
                static fn(AttributeValues $attributes, string $text) => new Background(new Vdt($files->read($text))),
            ),
            // A bug in the service's code, not in the page
            'broken' => self::builder(
                'broken',
                static fn() => throw new \InvalidArgumentException('a bug building <broken>'),
            ),
        ];
    }

    /**
     * @param \Closure(AttributeValues, string): Element $build
     */
    private static function builder(string $tag, \Closure $build): ElementBuilder
    {
        return new class($tag, $build) implements ElementBuilder {
            /**
             * @param \Closure(AttributeValues, string): Element $build
             */
            public function __construct(
                private readonly string $tag,
                private readonly \Closure $build,
            ) {}

            public function tag(): string
            {
                return $this->tag;
            }

            public function build(AttributeValues $attributes, string $text): Element
            {
                return ($this->build)($attributes, $text);
            }

            public function isPlacedByRow(): bool
            {
                return true;
            }

            public function isAllowedInLoop(): bool
            {
                return true;
            }
        };
    }
}
