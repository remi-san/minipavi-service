<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http\Dispatch;

use MiniPaviService\Http\Dispatch\ConditionalRequestHandler;
use MiniPaviService\Http\Dispatch\ConditionalRequestHandlers;
use MiniPaviService\Http\Dispatch\FromMiniPaviGateway;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\RecordingHandler;
use PHPUnit\Framework\TestCase;

final class ConditionalRequestHandlersTest extends TestCase
{
    public function testKeepsTheHandlersInTheirOrder(): void
    {
        $gateway = new ConditionalRequestHandler('gateway', new FromMiniPaviGateway(), new RecordingHandler('gateway'));
        $web = new ConditionalRequestHandler('web', new QueryParameterEquals('web', 'true'), new RecordingHandler('web'));

        $handlers = new ConditionalRequestHandlers($gateway, $web);

        self::assertCount(2, $handlers);
        self::assertSame([$gateway, $web], iterator_to_array($handlers));
        self::assertCount(0, new ConditionalRequestHandlers());
    }

    public function testAHandlerMatchesWhatItsMatcherMatchesAndDelegatesToItsHandler(): void
    {
        $handler = new ConditionalRequestHandler('web', new QueryParameterEquals('web', 'true'), new RecordingHandler('web'));

        self::assertSame('web', $handler->name());
        self::assertTrue($handler->matches(HttpRequests::to('Mozilla/5.0', ['web' => 'true'])));
        self::assertFalse($handler->matches(HttpRequests::to('Mozilla/5.0')));
        self::assertSame('web', (string) $handler->handle(HttpRequests::to('Mozilla/5.0'))->getBody());
    }
}
