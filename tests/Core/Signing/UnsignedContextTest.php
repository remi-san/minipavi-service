<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core\Signing;

use MiniPaviService\Core\Signing\UnsignedContext;
use PHPUnit\Framework\TestCase;

final class UnsignedContextTest extends TestCase
{
    public function testLeavesTheContextAsItIs(): void
    {
        $signer = new UnsignedContext();

        self::assertSame('{"route":"menu"}', $signer->sign('{"route":"menu"}'));
        self::assertSame('{"route":"menu"}', $signer->verify('{"route":"menu"}'));
    }
}
