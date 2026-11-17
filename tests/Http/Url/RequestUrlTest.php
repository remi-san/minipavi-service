<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http\Url;

use MiniPaviService\Http\Url\RequestUrl;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RequestUrlTest extends TestCase
{
    /**
     * A request with these server parameters, over those of /Svc/?code=1 on example.org
     *
     * @param array<string, string> $server
     */
    private static function request(array $server = []): ServerRequestInterface
    {
        $server += ['HTTP_HOST' => 'example.org', 'PHP_SELF' => '/Svc/index.php', 'REQUEST_URI' => '/Svc/?code=1'];
        $headers = isset($server['HTTP_X_FORWARDED_PROTO']) ? ['X-Forwarded-Proto' => $server['HTTP_X_FORWARDED_PROTO']] : [];

        return new ServerRequest('GET', '/Svc/?code=1', $headers, null, '1.1', $server);
    }

    public function testBuildsTheUrlsOfTheRequest(): void
    {
        $url = new RequestUrl();

        self::assertSame('http://example.org/Svc/index.php', $url->script(self::request()));
        self::assertSame('http://example.org/Svc/?code=1', $url->current(self::request()));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function schemes(): iterable
    {
        yield 'plain' => [[], 'http'];
        yield 'HTTPS on' => [['HTTPS' => 'on'], 'https'];
        yield 'HTTPS off' => [['HTTPS' => 'off'], 'http'];
        yield 'port 443' => [['SERVER_PORT' => '443'], 'https'];
        yield 'port 8080' => [['SERVER_PORT' => '8080'], 'http'];
        // Headers any client can send: ignored without a trusted proxy
        yield 'X-Forwarded-Proto' => [['HTTP_X_FORWARDED_PROTO' => 'https', 'REMOTE_ADDR' => '192.0.2.1'], 'http'];
        yield 'Front-End-Https' => [['HTTP_FRONT_END_HTTPS' => 'on'], 'http'];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('schemes')]
    public function testSchemeComesFromTheServer(array $server, string $scheme): void
    {
        self::assertSame($scheme . '://example.org/Svc/index.php', (new RequestUrl())->script(self::request($server)));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function proxies(): iterable
    {
        yield 'listed IP' => ['10.0.0.5', '10.0.0.5', 'https'];
        yield 'other IP' => ['10.0.0.5', '10.0.0.6', 'http'];
        yield 'IPv4 range' => ['127.0.0.1, 10.0.0.0/8', '10.20.30.40', 'https'];
        yield 'outside the range' => ['10.0.0.0/8', '11.0.0.1', 'http'];
        yield 'IPv6' => ['::1', '::1', 'https'];
        yield 'IPv6 range' => ['fd00::/8', 'fd12:3456::1', 'https'];
        yield 'IPv6 address, IPv4 range' => ['10.0.0.0/8', '::1', 'http'];
    }

    #[DataProvider('proxies')]
    public function testForwardedProtoComesOnlyFromTrustedProxies(string $trusted, string $remote, string $scheme): void
    {
        $request = self::request(['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_PROTO' => 'https']);

        self::assertSame($scheme . '://example.org/Svc/index.php', (new RequestUrl(trustedProxies: $trusted))->script($request));
    }

    public function testServiceUrlReplacesTheSchemeAndHost(): void
    {
        $request = self::request(['HTTP_HOST' => 'attacker.example', 'HTTP_X_FORWARDED_PROTO' => 'http']);
        $url = new RequestUrl('https://minitel.example.org:8443/');

        self::assertSame('https://minitel.example.org:8443/Svc/index.php', $url->script($request));
        self::assertSame('https://minitel.example.org:8443/Svc/?code=1', $url->current($request));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidServiceUrls(): iterable
    {
        yield 'no scheme' => ['minitel.example.org'];
        yield 'other scheme' => ['ftp://minitel.example.org'];
        yield 'with a path' => ['https://minitel.example.org/Svc'];
        yield 'with a query' => ['https://minitel.example.org/?a=1'];
    }

    #[DataProvider('invalidServiceUrls')]
    public function testServiceUrlMustBeAnOrigin(string $serviceUrl): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SERVICE_URL');

        new RequestUrl($serviceUrl);
    }

    public function testInvalidTrustedProxyIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TRUSTED_PROXIES: "proxy.local" is not an IP address or range');

        new RequestUrl(trustedProxies: '10.0.0.1, proxy.local');
    }
}
