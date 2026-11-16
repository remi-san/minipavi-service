<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Http\RequestUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestUrlTest extends TestCase
{
    /** @var array<mixed> */
    private array $server;

    /** @var array<string, string|false> */
    private array $environment = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        foreach (['HTTPS', 'SERVER_PORT', 'HTTP_X_FORWARDED_PROTO', 'HTTP_FRONT_END_HTTPS', 'REMOTE_ADDR'] as $name) {
            unset($_SERVER[$name]);
        }
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['PHP_SELF'] = '/Svc/index.php';
        $_SERVER['REQUEST_URI'] = '/Svc/?code=1';
        foreach (['SERVICE_URL', 'TRUSTED_PROXIES'] as $name) {
            $this->environment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }
    }

    public function testBuildsTheUrlsOfTheRequest(): void
    {
        $url = new RequestUrl();

        self::assertSame('http://example.org/Svc/index.php', $url->script());
        self::assertSame('http://example.org/Svc/?code=1', $url->current());
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
        $_SERVER = array_merge($_SERVER, $server);

        self::assertSame($scheme . '://example.org/Svc/index.php', (new RequestUrl())->script());
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
        $_SERVER['REMOTE_ADDR'] = $remote;
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        self::assertSame($scheme . '://example.org/Svc/index.php', (new RequestUrl(trustedProxies: $trusted))->script());
    }

    public function testServiceUrlReplacesTheSchemeAndHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'attacker.example';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $url = new RequestUrl('https://minitel.example.org:8443/');

        self::assertSame('https://minitel.example.org:8443/Svc/index.php', $url->script());
        self::assertSame('https://minitel.example.org:8443/Svc/?code=1', $url->current());
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

    public function testFromEnvironmentReadsServiceUrlAndTrustedProxies(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        self::assertSame('http://example.org/Svc/index.php', RequestUrl::fromEnvironment()->script());

        putenv('TRUSTED_PROXIES=10.0.0.5');
        self::assertSame('https://example.org/Svc/index.php', RequestUrl::fromEnvironment()->script());

        putenv('SERVICE_URL=https://minitel.example.org');
        self::assertSame('https://minitel.example.org/Svc/index.php', RequestUrl::fromEnvironment()->script());
    }
}
