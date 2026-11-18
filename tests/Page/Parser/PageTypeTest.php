<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Page\Parser\PageType;
use PHPUnit\Framework\TestCase;

final class PageTypeTest extends TestCase
{
    public function testIsTheTypeAttributeOfAPage(): void
    {
        // What the page XML format (and its schema) knows
        self::assertSame(
            ['static', 'template'],
            array_map(static fn(PageType $type) => $type->value, PageType::cases()),
        );
    }

    public function testOnlyATemplateHasPlaceholdersAndInstructions(): void
    {
        self::assertTrue(PageType::Template->isTemplate());
        self::assertFalse(PageType::Static->isTemplate());
    }
}
