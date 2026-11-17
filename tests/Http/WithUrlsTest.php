<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Http\UrlAttribute;
use MiniPaviService\Http\WithUrls;
use MiniPaviService\Tests\Support\RecordingHandler;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class WithUrlsTest extends TestCase
{
    public function testGivesTheRequestWithItsUrls(): void
    {
        $handler = new RecordingHandler();
        $request = new ServerRequest('GET', '/Svc/?web=true', [], null, '1.1', ['HTTP_HOST' => 'example.org', 'PHP_SELF' => '/Svc/index.php', 'REQUEST_URI' => '/Svc/?web=true']);

        (new WithUrls($handler, new RequestUrl()))->handle($request);

        self::assertSame('http://example.org/Svc/index.php', UrlAttribute::Service->of($handler->requests[0]));
        self::assertSame('http://example.org/Svc/?web=true', UrlAttribute::Current->of($handler->requests[0]));
    }

    public function testUrlsAreOnlyThoseOfWithUrls(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No minipavi.serviceUrl attribute: the request must go through WithUrls');

        UrlAttribute::Service->of(new ServerRequest('GET', '/'));
    }
}
