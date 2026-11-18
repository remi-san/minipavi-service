<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Page\Parser\Element\ElementBuilder;
use MiniPaviService\Page\Parser\PageXmlException;

/**
 * Display element of a template (<text>, <line>, <key>, <endkey>, <background>, <image>...),
 * whose text and attributes may contain {{placeholders}}, built by its ElementBuilder once
 * they are replaced
 */
final readonly class ElementNode implements Node
{
    /**
     * @param array<string, string> $attributes name => value, as in the XML file
     * @param string $location file and line, for error messages
     */
    public function __construct(
        private ElementBuilder $builder,
        private array $attributes,
        private string $text,
        private string $location,
    ) {}

    public function elements(Scope $scope): array
    {
        try {
            $attributes = new AttributeValues(array_map($scope->substitute(...), $this->attributes));
            // Inside a <foreach>, row is relative to the current item (0 = its first row)
            if ($scope->rowOffset !== null && $this->builder->isPlacedByRow()) {
                $attributes = $attributes->with(
                    'row',
                    (string) ($scope->rowOffset + $attributes->int('row', default: 0)),
                );
            }

            return [$this->builder->build($attributes, $scope->substitute($this->text))];
        } catch (PageXmlException|\InvalidArgumentException $e) {
            throw new TemplateException("{$this->location}: <{$this->builder->tag()}>: " . $e->getMessage(), 0, $e);
        }
    }
}
