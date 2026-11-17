<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Response the Kernel doesn't send: the answer was already written (MiniPaviCli::send(), for
 * the gateway), or there is nothing to answer. A 204 otherwise.
 */
final readonly class EmptyHttpResponse implements ResponseInterface
{
    public function __construct(
        private ResponseInterface $response = new Response(204),
    ) {}

    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        return new static($this->response->withProtocolVersion($version));
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader(string $name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        return new static($this->response->withHeader($name, $value));
    }

    public function withAddedHeader(string $name, $value): static
    {
        return new static($this->response->withAddedHeader($name, $value));
    }

    public function withoutHeader(string $name): static
    {
        return new static($this->response->withoutHeader($name));
    }

    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        return new static($this->response->withBody($body));
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        return new static($this->response->withStatus($code, $reasonPhrase));
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }
}
