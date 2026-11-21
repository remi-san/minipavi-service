<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\ElementBuilder;
use MiniPaviService\Page\Building\PageXmlException;

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
            // Inside a <foreach>, row is relative to the current item
            if ($this->builder->isPlacedByRow()) {
                $attributes = $scope->placedByRow($attributes);
            }

            return [$this->builder->build($attributes, $scope->substitute($this->text))];
        } catch (PageXmlException|InvalidValueException $e) {
            throw new TemplateException(
                "{$this->location}: <{$this->builder->tag()}>: " . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
