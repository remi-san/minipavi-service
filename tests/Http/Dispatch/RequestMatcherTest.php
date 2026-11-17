<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http\Dispatch;

use MiniPaviService\Http\Dispatch\FromMiniPaviGateway;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Tests\Support\HttpRequests;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RequestMatcherTest extends TestCase
{
    public function testFromMiniPaviGatewayMatchesItsUserAgent(): void
    {
        self::assertTrue((new FromMiniPaviGateway())->matches(HttpRequests::to('MiniPAVI')));
        self::assertTrue((new FromMiniPaviGateway())->matches(HttpRequests::to('Mozilla/5.0 (MiniPAVI gateway)')));
        self::assertFalse((new FromMiniPaviGateway())->matches(HttpRequests::to('Mozilla/5.0')));
        self::assertFalse((new FromMiniPaviGateway())->matches(new ServerRequest('GET', '/')));
    }

    public function testQueryParameterEqualsMatchesThatValueOnly(): void
    {
        $matcher = new QueryParameterEquals('web', 'true');

        self::assertTrue($matcher->matches(HttpRequests::to('Mozilla/5.0', ['web' => 'true'])));
        self::assertFalse($matcher->matches(HttpRequests::to('Mozilla/5.0', ['web' => 'TRUE'])));
        self::assertFalse($matcher->matches(HttpRequests::to('Mozilla/5.0', ['web' => '1'])));
        self::assertFalse($matcher->matches(HttpRequests::to('Mozilla/5.0')));
        // Not text (web[]=true)
        self::assertFalse($matcher->matches((new ServerRequest('GET', '/'))->withQueryParams(['web' => ['true']])));
    }
}
