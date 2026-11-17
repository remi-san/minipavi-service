<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Decides who the HTTP request is for: the handler of the first of its rules whose matcher
 * matches it, or else its fallback. A service gives its own rules, e.g. the gateway's requests
 * (FromMiniPaviGateway) to a ServiceRunner with MiniPaviClient, browsers asking ?web=true
 * (QueryParameterEquals) to one with HtmlClient, and other browsers to the fallback
 * (EmulatorRedirect).
 */
final readonly class Dispatcher implements RequestHandlerInterface
{
    private LoggerInterface $logger;

    /**
     * @param RequestHandlerInterface $fallback handles the requests no rule matches
     * @param list<Rule> $rules tried in this order
     * @param ?LoggerInterface $logger where the rule handling each request is logged (debug);
     *                                 none by default
     */
    public function __construct(
        private RequestHandlerInterface $fallback,
        private array $rules,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        foreach ($this->rules as $rule) {
            if ($rule->matcher->matches($request)) {
                $this->logger->debug('Request dispatched', ['rule' => $rule->name]);

                return $rule->handler->handle($request);
            }
        }

        $this->logger->debug('Request dispatched', ['rule' => 'fallback']);

        return $this->fallback->handle($request);
    }
}
