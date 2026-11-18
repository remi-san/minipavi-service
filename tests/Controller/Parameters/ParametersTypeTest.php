<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller\Parameters;

use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\ParametersType;
use MiniPaviService\Controller\Parameters\Values;
use PHPUnit\Framework\TestCase;

final class ParametersTypeTest extends TestCase
{
    public function testReadsItsParametersFromValues(): void
    {
        $type = ParametersType::of(PageParameters::class);

        self::assertEquals(new PageParameters(3), $type->fromValues(new Values(['page' => 3])));
        self::assertEquals(new PageParameters(), $type->defaults());
    }

    public function testAcceptsItsParametersOnly(): void
    {
        $type = ParametersType::of(PageParameters::class);

        self::assertTrue($type->accepts(new PageParameters(1)));
        self::assertFalse($type->accepts(new NoParameters()));
        self::assertSame(PageParameters::class, $type->name());
    }

    public function testNoneIsNoParameters(): void
    {
        self::assertEquals(new NoParameters(), ParametersType::none()->defaults());
        self::assertTrue(ParametersType::none()->accepts(new NoParameters()));
    }
}
