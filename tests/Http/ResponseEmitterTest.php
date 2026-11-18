<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Http\ResponseEmitter;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * In separate processes: the status can only be set before any output. The headers aren't
 * kept by PHP's command line (headers_list() is empty there), and PHPUnit buffers the output
 * (so the refusal after output started can't be reached): both checked in the stack.
 */
final class ResponseEmitterTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSendsTheStatusThenTheBody(): void
    {
        ob_start();
        try {
            (new ResponseEmitter())->emit(new Response(302, ['Location' => 'http://emulator/'], 'moved'));
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame(302, http_response_code());
        self::assertSame('moved', $output);
    }

    #[RunInSeparateProcess]
    public function testSendsALongBodyWhole(): void
    {
        $body = str_repeat('0123456789', times: 2000);
        $response = new Response(200, [], $body);
        // Already read once: sent from its start
        (string) $response->getBody();

        ob_start();
        try {
            (new ResponseEmitter())->emit($response);
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame($body, $output);
    }
}
