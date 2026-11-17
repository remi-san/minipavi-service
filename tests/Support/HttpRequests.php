<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Http\Url\UrlAttribute;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP requests for the tests, as the Kernel would give them
 */
final class HttpRequests
{
    /**
     * A request of the gateway, or of a browser, with the URLs WithUrls would have computed
     *
     * @param array<string, string> $query
     */
    public static function to(
        string $userAgent,
        array $query = [],
        string $serviceUrl = 'http://services/Svc/index.php',
    ): ServerRequestInterface {
        return self::posting($userAgent, $query, [], $serviceUrl);
    }

    /**
     * A request posting a form (e.g. a key pressed in the HTML view), with the URLs WithUrls
     * would have computed
     *
     * @param array<string, string> $query
     * @param array<string, mixed> $form
     */
    public static function posting(
        string $userAgent,
        array $query,
        array $form,
        string $serviceUrl = 'http://services/Svc/index.php',
    ): ServerRequestInterface {
        $current = 'http://services/Svc/' . ($query === [] ? '' : '?' . http_build_query($query));

        return (new ServerRequest($form === [] ? 'GET' : 'POST', $current, ['User-Agent' => $userAgent]))
            ->withQueryParams($query)
            ->withParsedBody($form === [] ? null : $form)
            ->withAttribute(UrlAttribute::Service->value, $serviceUrl)
            ->withAttribute(UrlAttribute::Current->value, $current);
    }
}
