<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Sends a PSR-7 response to the client of the web server: its status, its headers, then its
 * body. With the Kernel reading the request, the only place writing headers and output (but
 * MiniPaviCli, for the gateway).
 */
final class ResponseEmitter
{
    // Bytes of the body written at a time
    private const CHUNK = 8192;

    /**
     * @throws \LogicException if output was already sent: the status and headers can't be
     */
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent($file, $line)) {
            throw new \LogicException("Cannot send the response: output already started at $file:$line");
        }

        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $i => $value) {
                // Each value of a header on its own line (e.g. Set-Cookie)
                header("$name: $value", $i === 0);
            }
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        while (!$body->eof()) {
            echo $body->read(self::CHUNK);
        }
    }
}
