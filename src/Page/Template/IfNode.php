<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

/**
 * <if test="loop.last"> or <if test="!loop.last">: draws its children only if the
 * variable is set to something other than false, 0, an empty text or an empty list
 * (or, with "!", only if it is not)
 */
final readonly class IfNode implements Node
{
    /**
     * @param string $test variable to check
     * @param bool $negate true for test="!variable"
     * @param Node[] $children
     */
    public function __construct(
        private string $test,
        private bool $negate,
        private array $children,
        private string $location,
    ) {}

    public function elements(Scope $scope): array
    {
        try {
            $value = $scope->value($this->test);
        } catch (\InvalidArgumentException $e) {
            throw new TemplateException("$this->location: <if>: " . $e->getMessage(), 0, $e);
        }
        $true = $value instanceof \Countable ? count($value) > 0 : (bool) $value;
        if ($true === $this->negate) {
            return [];
        }

        $elements = [];
        foreach ($this->children as $child) {
            array_push($elements, ...$child->elements($scope));
        }
        return $elements;
    }
}
