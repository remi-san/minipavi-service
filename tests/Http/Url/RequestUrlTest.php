<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http\Url;

use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\InvalidValueException;
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
        $headers = array_key_exists('HTTP_X_FORWARDED_PROTO', $server)
            ? ['X-Forwarded-Proto' => $server['HTTP_X_FORWARDED_PROTO']]
            : [];

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
        // Whatever its case (IIS sends "OFF" without TLS)
        yield 'HTTPS OFF' => [['HTTPS' => 'OFF'], 'http'];
        yield 'HTTPS 0' => [['HTTPS' => '0'], 'http'];
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
        // A range of every IPv4 address doesn't take in IPv6 ones
        yield 'IPv6 address, every IPv4 address' => ['0.0.0.0/0', '::1', 'http'];
        yield 'every IPv4 address' => ['0.0.0.0/0', '203.0.113.9', 'https'];
        // A prefix ending inside a byte: 10.0.0.0 to 10.1.255.255
        yield 'in a /15' => ['10.0.0.0/15', '10.1.200.1', 'https'];
        yield 'outside a /15' => ['10.0.0.0/15', '10.2.0.1', 'http'];
        // Written with the bits after the prefix set: the same range
        yield 'in a /15 written from inside it' => ['10.1.0.0/15', '10.0.200.1', 'https'];
        yield 'a /32' => ['10.0.0.5/32', '10.0.0.5', 'https'];
        yield 'a /128' => ['::1/128', '::1', 'https'];
        yield 'empty entries' => [' , 10.0.0.5 ,', '10.0.0.5', 'https'];
        // E.g. a Unix socket: not trusted
        yield 'not an IP address' => ['10.0.0.0/8', 'unix:/run/php.sock', 'http'];
        // Taken by some systems' inet_pton() (macOS) as 10.0.0.1
        yield 'leading zeros' => ['10.0.0.0/8', '010.0.0.1', 'http'];
    }

    #[DataProvider('proxies')]
    public function testForwardedProtoComesOnlyFromTrustedProxies(string $trusted, string $remote, string $scheme): void
    {
        $request = self::request(['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_PROTO' => 'https']);

        self::assertSame(
            $scheme . '://example.org/Svc/index.php',
            (new RequestUrl(trustedProxies: $trusted))->script($request),
        );
    }

    public function testForwardedProtoIsReadWhateverItsCase(): void
    {
        $request = self::request(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_PROTO' => 'HTTPS']);

        self::assertSame(
            'https://example.org/Svc/index.php',
            (new RequestUrl(trustedProxies: '10.0.0.5'))->script($request),
        );
    }

    public function testServiceUrlReplacesTheSchemeAndHost(): void
    {
        $request = self::request(['HTTP_HOST' => 'attacker.example', 'HTTP_X_FORWARDED_PROTO' => 'http']);
        $url = new RequestUrl('https://minitel.example.org:8443/');

        self::assertSame('https://minitel.example.org:8443/Svc/index.php', $url->script($request));
        self::assertSame('https://minitel.example.org:8443/Svc/?code=1', $url->current($request));
    }

    public function testServiceUrlSchemeIsLowercased(): void
    {
        self::assertSame(
            'https://minitel.example.org/Svc/index.php',
            (new RequestUrl('HTTPS://minitel.example.org'))->script(self::request()),
        );
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
        yield 'with a fragment' => ['https://minitel.example.org/#top'];
        yield 'with credentials' => ['https://user:secret@minitel.example.org'];
        yield 'without host' => ['https:/minitel'];
        // parse_url() can't read it
        yield 'malformed' => ['http:///minitel.example.org'];
    }

    #[DataProvider('invalidServiceUrls')]
    public function testServiceUrlMustBeAnOrigin(string $serviceUrl): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('SERVICE_URL');

        new RequestUrl($serviceUrl);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProxies(): iterable
    {
        yield 'a host name' => ['proxy.local'];
        yield 'an IPv4 prefix too long' => ['10.0.0.0/33'];
        yield 'an IPv6 prefix too long' => ['fd00::/129'];
        yield 'a prefix not a number' => ['10.0.0.0/eight'];
        yield 'a negative prefix' => ['10.0.0.0/-1'];
        yield 'an empty prefix' => ['10.0.0.0/'];
        yield 'two prefixes' => ['10.0.0.0/8/16'];
        yield 'a malformed address' => ['10.0.0/8'];
        // Taken by some systems' inet_pton() (macOS), not by others (Linux)
        yield 'leading zeros' => ['010.0.0.1'];
        yield 'a zone index' => ['fe80::1%en0'];
    }

    #[DataProvider('invalidProxies')]
    public function testInvalidTrustedProxyIsRefused(string $proxy): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("TRUSTED_PROXIES: \"{$proxy}\" is not an IP address or range");

        new RequestUrl(trustedProxies: "10.0.0.1, {$proxy}");
    }
}
