<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use MiniPaviService\Client\HtmlClient;
use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Signing\ContextSigner;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Core\Signing\UnsignedContext;
use MiniPaviService\Http\Dispatch\ConditionalRequestHandler;
use MiniPaviService\Http\Dispatch\Dispatcher;
use MiniPaviService\Http\Dispatch\FromMiniPaviGateway;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\Emulator\EmulatorRedirect;
use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\Http\Url\WithUrls;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builds the Kernel a service's public/index.php runs, from its router and messages, with the
 * framework's wiring: the gateway's requests run the service with MiniPaviClient, browsers
 * asking ?web=true with HtmlClient, and the others are sent to the emulator (EmulatorRedirect);
 * the URLs are computed by WithUrls, the request created with nyholm/psr7-server.
 *
 * Each with...() gives a builder changing one thing; the defaults: no logger, the context not
 * signed (with a warning), the request's URL as it comes, the minipavi.fr emulator and gateway,
 * no other handler.
 */
final readonly class KernelBuilder
{
    /**
     * @param string $contextKey key signing the context (HmacContextSigner); '': not signed
     * @param list<ConditionalRequestHandler> $handlers the service's, tried first
     */
    private function __construct(
        private Router $router,
        private Messages $messages,
        private LoggerInterface $logger,
        private string $contextKey,
        private RequestUrl $requestUrl,
        private EmulatorConfig $emulator,
        private array $handlers,
    ) {}

    /**
     * @param Messages $messages the service's, e.g. its messages.xml over Messages::defaults()
     */
    public static function for(Router $router, Messages $messages): self
    {
        return new self($router, $messages, new NullLogger(), '', new RequestUrl(), new EmulatorConfig(), []);
    }

    /**
     * Where connections, disconnections, errors and the handler of each request are logged
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return new self($this->router, $this->messages, $logger, $this->contextKey, $this->requestUrl, $this->emulator, $this->handlers);
    }

    /**
     * The secret key signing the context (HmacContextSigner), so that it can't be forged, e.g.
     * read from the environment; '' doesn't sign it, with a warning on each request
     */
    public function withContextKey(#[\SensitiveParameter] string $key): self
    {
        return new self($this->router, $this->messages, $this->logger, $key, $this->requestUrl, $this->emulator, $this->handlers);
    }

    /**
     * How the service's URLs are computed (e.g. behind a reverse proxy)
     */
    public function withRequestUrl(RequestUrl $requestUrl): self
    {
        return new self($this->router, $this->messages, $this->logger, $this->contextKey, $requestUrl, $this->emulator, $this->handlers);
    }

    /**
     * The emulator browsers are sent to (e.g. the local stack's)
     */
    public function withEmulator(EmulatorConfig $emulator): self
    {
        return new self($this->router, $this->messages, $this->logger, $this->contextKey, $this->requestUrl, $emulator, $this->handlers);
    }

    /**
     * A handler of the service (e.g. an API, a health check), tried before the framework's, in
     * the order they are added
     */
    public function withHandler(ConditionalRequestHandler $handler): self
    {
        return new self($this->router, $this->messages, $this->logger, $this->contextKey, $this->requestUrl, $this->emulator, [...$this->handlers, $handler]);
    }

    /**
     * @throws MessagesException if the messages miss some of the framework's
     */
    public function build(): Kernel
    {
        $psr17 = new Psr17Factory();

        return new Kernel($this->handler(), new ServerRequestCreator($psr17, $psr17, $psr17, $psr17), new ResponseEmitter());
    }

    /**
     * What the Kernel has handle the request: WithUrls, then the Dispatcher
     *
     * @throws MessagesException if the messages miss some of the framework's
     */
    private function handler(): RequestHandlerInterface
    {
        $service = new Service($this->router, $this->messages, $this->logger);
        $signer = $this->signer();

        return new WithUrls(
            new Dispatcher(
                [
                    ...$this->handlers,
                    new ConditionalRequestHandler('gateway', new FromMiniPaviGateway(), new ServiceRunner($service, new MiniPaviClient(), $signer, $this->logger)),
                    new ConditionalRequestHandler('web', new QueryParameterEquals('web', 'true'), new ServiceRunner($service, new HtmlClient(new HtmlRenderer(HtmlRenderer::MINITEL_FONT), $this->messages), $signer, $this->logger)),
                ],
                new EmulatorRedirect($this->emulator, $this->logger),
                $this->logger,
            ),
            $this->requestUrl,
        );
    }

    private function signer(): ContextSigner
    {
        if ($this->contextKey === '') {
            $this->logger->warning('No key signing the context: it is not signed, and can be forged');

            return new UnsignedContext();
        }

        return new HmacContextSigner($this->contextKey);
    }
}
