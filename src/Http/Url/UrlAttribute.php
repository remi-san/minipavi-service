<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Url;

use Psr\Http\Message\ServerRequestInterface;

/**
 * URLs of the service computed for a request (RequestUrl) by WithUrls, and kept in its
 * attributes: the handlers after it read them here, without parsing the request again
 */
enum UrlAttribute: string
{
    // URL of the script serving the request, without query: the next URL for the gateway
    case Service = 'minipavi.serviceUrl';
    // URL asked for, with its query: where the emulator sends browsers back
    case Current = 'minipavi.currentUrl';

    /**
     * The URL in the request's attributes
     *
     * @throws \LogicException if the request didn't go through WithUrls
     */
    public function of(ServerRequestInterface $request): string
    {
        $url = $request->getAttribute($this->value);

        return \is_string($url)
            ? $url
            : throw new \LogicException("No {$this->value} attribute: the request must go through WithUrls");
    }
}
