<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use MiniPaviService\Client\Html\HtmlClient;
use MiniPaviService\Client\Html\HtmlForm;
use MiniPaviService\Client\MiniPavi\MiniPaviClient;
use MiniPaviService\Client\MiniPavi\MiniPaviCliGateway;
use MiniPaviService\Core\Context\ContextSerializer;
use MiniPaviService\Core\Context\Signing\ContextSigner;
use MiniPaviService\Core\Context\Signing\HmacContextSigner;
use MiniPaviService\Core\Context\Signing\UnsignedContext;
use MiniPaviService\Core\Routing\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Sessions;
use MiniPaviService\Http\Dispatch\ConditionalRequestHandler;
use MiniPaviService\Http\Dispatch\Dispatcher;
use MiniPaviService\Http\Dispatch\FromMiniPaviGateway;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\Emulator\EmulatorRedirect;
use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\Http\Url\WithUrls;
use MiniPaviService\InvalidValueException;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
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
 * no other handler, Service's maximum of page changes, and the Minitel font of the HTML client.
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
        private int $maxPageChanges,
        private string $fontFile,
    ) {}

    /**
     * @param Messages $messages the service's, e.g. its messages.xml over MessagesXmlParser::defaults()
     */
    public static function for(Router $router, Messages $messages): self
    {
        return new self(
            $router,
            $messages,
            new NullLogger(),
            contextKey: '',
            requestUrl: new RequestUrl(),
            emulator: new EmulatorConfig(),
            handlers: [],
            maxPageChanges: Sessions::MAX_PAGE_CHANGES,
            fontFile: HtmlRenderer::MINITEL_FONT,
        );
    }

    /**
     * Where connections, disconnections, errors and the handler of each request are logged
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(logger: $logger);
    }

    /**
     * The secret key signing the context (HmacContextSigner), so that it can't be forged, e.g.
     * read from the environment; '' doesn't sign it, with a warning on each request
     */
    public function withContextKey(#[\SensitiveParameter] string $key): self
    {
        return $this->with(contextKey: $key);
    }

    /**
     * How the service's URLs are computed (e.g. behind a reverse proxy)
     */
    public function withRequestUrl(RequestUrl $requestUrl): self
    {
        return $this->with(requestUrl: $requestUrl);
    }

    /**
     * The emulator browsers are sent to (e.g. the local stack's)
     */
    public function withEmulator(EmulatorConfig $emulator): self
    {
        return $this->with(emulator: $emulator);
    }

    /**
     * A handler of the service (e.g. an API, a health check), tried before the framework's, in
     * the order they are added
     */
    public function withHandler(ConditionalRequestHandler $handler): self
    {
        return $this->with(handlers: [...$this->handlers, $handler]);
    }

    /**
     * Page changes (ChangePage) allowed in one request, more being taken as controllers
     * changing to each other endlessly (Sessions); 0 forbids them, and a negative number
     * makes build() throw
     */
    public function withMaxPageChanges(int $maxPageChanges): self
    {
        return $this->with(maxPageChanges: $maxPageChanges);
    }

    /**
     * The TrueType font of the HTML client's screen (HtmlRenderer), instead of the Minitel font
     * of the framework. Checked here, as HtmlRenderer only reads it for each browser's request
     *
     * @throws InvalidValueException if the font file can't be read
     */
    public function withFont(string $fontFile): self
    {
        if (!is_file($fontFile) || !is_readable($fontFile)) {
            throw new InvalidValueException("Font file not found: {$fontFile}");
        }

        return $this->with(fontFile: $fontFile);
    }

    /**
     * A copy with the values given, the others as they are: what each with...() changes. Null
     * means "not given", so this can't set an option back to null: a nullable option would
     * need its own copy
     *
     * @param ?list<ConditionalRequestHandler> $handlers
     */
    private function with(
        ?LoggerInterface $logger = null,
        #[\SensitiveParameter]
        ?string $contextKey = null,
        ?RequestUrl $requestUrl = null,
        ?EmulatorConfig $emulator = null,
        ?array $handlers = null,
        ?int $maxPageChanges = null,
        ?string $fontFile = null,
    ): self {
        return new self(
            $this->router,
            $this->messages,
            $logger ?? $this->logger,
            $contextKey ?? $this->contextKey,
            $requestUrl ?? $this->requestUrl,
            $emulator ?? $this->emulator,
            $handlers ?? $this->handlers,
            $maxPageChanges ?? $this->maxPageChanges,
            $fontFile ?? $this->fontFile,
        );
    }

    /**
     * @throws MessagesException if the messages miss some of the framework's
     * @throws InvalidValueException if the max page changes are negative (Sessions)
     */
    public function build(): Kernel
    {
        $psr17 = new Psr17Factory();

        return new Kernel(
            $this->handler(),
            new ServerRequestCreator($psr17, $psr17, $psr17, $psr17),
            new ResponseEmitter(),
        );
    }

    /**
     * What the Kernel has handle the request: WithUrls, then the Dispatcher
     *
     * @throws MessagesException if the messages miss some of the framework's
     * @throws InvalidValueException if the max page changes are negative (Sessions)
     */
    private function handler(): RequestHandlerInterface
    {
        $service = new Service(
            new Sessions(new ContextSerializer($this->router, $this->signer()), $this->router, $this->maxPageChanges),
            $this->messages,
            $this->logger,
        );

        return new WithUrls(
            new Dispatcher(
                [
                    ...$this->handlers,
                    new ConditionalRequestHandler(
                        'gateway',
                        new FromMiniPaviGateway(),
                        new ServiceRunner($service, new MiniPaviClient(new MiniPaviCliGateway()), $this->logger),
                    ),
                    new ConditionalRequestHandler(
                        'web',
                        new QueryParameterEquals('web', 'true'),
                        new ServiceRunner(
                            $service,
                            new HtmlClient(
                                new HtmlForm(new HtmlRenderer($this->fontFile), $this->messages),
                                $this->messages,
                            ),
                            $this->logger,
                        ),
                    ),
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
