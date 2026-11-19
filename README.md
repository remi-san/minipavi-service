# MiniPavi service framework

PHP framework for Minitel services behind a [MiniPavi](https://www.minipavi.fr/) gateway. It builds on the
official [`ludosevilla/minipavi-cli`](https://packagist.org/packages/ludosevilla/minipavi-cli).

- **Pages** described in XML: static, or templates with placeholders, loops and conditions
- **Controllers**, one per screen, each a small state machine returning **responses**
- A **router** and a **context** keeping each controller's state between requests
- A **service without I/O**: it responds to a `GatewayRequest` with a `GatewayResponse`, which
  `MiniPaviClient` reads from and sends to the gateway, through the static `MiniPaviCli`
- **`Vdt`**, to read Videotex content (e.g. pictures made with a Videotex editor) and move it on the screen

Requires PHP 8.2.

The code (namespace `MiniPaviService`, in `src/`) is organized by layer. The runtime uses the
controllers, which use the pages, and none uses those above it: `Core\` → `Controller\` →
`Page\` → `Message\`, `Minitel\`, `Vdt\`, and the clients and the entry point sit on top:
`Client\` → `Http\` → `Core\` (`Gateway\`'s requests and responses are values all of them use,
which depend on nothing above them). Below `Page\`, `Vdt\` uses `Minitel\`, and neither uses
the layers above it (`LayersTest` checks it):

| Namespace | Holds |
|---|---|
| `MiniPaviService` | the `Controller` and `Page` interfaces, and the `InvalidValueException` of a value the framework can't take (e.g. a `Position` off the screen) |
| `Core\` | the runtime: `Service`, `Router` and its `Route`s, `Context` and its `RouteContext`s, `RequestFlow` |
| `Controller\` | `AbstractController`, `Request`, `Response` and the response types, `Flow`, the states (`State`, `ControllerState`) |
| `Controller\Parameters\` | what a controller keeps: `Parameters`, `NoParameters`, their `ParametersType` (what `Controller::parametersType()` returns), and the raw `Values` they are read from |
| `Controller\Pagination\` | `PaginatedController`, the `PaginatedList` it shows and its `ShownPage`, `Paginator`, `PageParameters` |
| `Page\` | static and template pages, their display elements, and the page XML parser |
| `Message\` | the messages shown on screen: `Message`, `FrameworkMessage`, `Messages` and their XML parser |
| `Minitel\` | the user's terminal: `Terminal`, its `FunctionKey`s, its settings (`Keyboard`, `LocalEcho`, `Scroll`), its `ScreenSize` and `Color`s, and the `InputField` where the user types |
| `Vdt\` | Videotex: codes, reading, writing and moving Videotex content (`Vdt`, `Videotex`); in `Screen\`, the Minitel screen it draws (`Screen`, `Cell`) and its HTML rendering (`HtmlRenderer`) |
| `Gateway\` | the requests and responses the service handles (`GatewayRequest`, `GatewayResponse`), the commands a response sends (`Command`: the `WaitForKeyCommand` waiting for a key, the `InputCommand` reading a field, the `ServiceCallCommand` calling a service, with its `CallMode`), and the `Client` interface reading and sending them |
| `Client\` | the clients: `MiniPaviClient` for the MiniPavi gateway, `HtmlClient` for a browser (`?web=true`) |
| `Http\` | the entry point for the web server, on PSR-7 / PSR-15: `Kernel`, `ResponseEmitter`, `ServiceRunner`; in `Dispatch\`, who handles the request (`Dispatcher`, `ConditionalRequestHandler`, `RequestMatcher`s); in `Url\`, the request's URLs (`RequestUrl`, `UrlAttribute`, `WithUrls`); in `Emulator\`, the emulator browsers are sent to (`EmulatorConfig`, `EmulatorRedirect`) |
| `Testing\` | `ServiceTester`, functional tests of a service (needs PHPUnit) |

## Installing

From [Packagist](https://packagist.org/packages/remi-san/minipavi-service), at its `main`
branch (or a release, e.g. `^0.1`):

```json
{
    "require": { "remi-san/minipavi-service": "dev-main" }
}
```

To work on the framework and a service together, point the service at a local clone with a
`path` repository (Composer symlinks it into `vendor/`, so edits to the framework apply without
reinstalling), and don't commit it:

```json
{
    "repositories": [{ "type": "path", "url": "../minipavi-service" }]
}
```

The skeleton, [remi-san/minipavi-service-skeleton](https://github.com/remi-san/minipavi-service-skeleton), uses it this way.

## Writing a service

`public/index.php` builds the pages and controllers, and hands them to the service:

```php
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\KernelBuilder;
use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Page\Parser\PageXmlParser;

require __DIR__.'/../vendor/autoload.php';

// The framework's messages; a service usually reads its own over them (see Messages)
$messages = (new MessagesXmlParser())->defaults();

// New connections start at the default route; build() fails if it isn't one of the routes
$router = (new RouterBuilder(HomeController::NAME))
    ->addRoute(HomeController::NAME, new HomeController((new PageXmlParser($messages))->parseFile(__DIR__.'/../config/pages/home.xml')))
    ->build();

// The environment is read here (or in the container): the framework doesn't read it itself
KernelBuilder::for($router, $messages)
    ->withLogger(new Logger('my-service', [new ErrorLogHandler()]))   // Monolog
    ->withContextKey(getenv('CONTEXT_SECRET') ?: '')
    ->withRequestUrl(new RequestUrl(getenv('SERVICE_URL') ?: null, getenv('TRUSTED_PROXIES') ?: ''))
    ->withEmulator(EmulatorConfig::fromValues(getenv('EMULATOR_URL') ?: null, getenv('GATEWAY_URL') ?: null, getenv('SERVICES_INTERNAL_HOST') ?: null))
    ->build()
    ->run();
```

- **`Http\KernelBuilder`** builds the `Kernel` with the framework's wiring, described below:
  a `Service` for the router and messages; the gateway's requests run it with
  `MiniPaviClient`, browsers asking `?web=true` with `HtmlClient`, the others are sent to the
  emulator (`EmulatorRedirect`); the URLs are computed by `WithUrls`, the request created with
  `nyholm/psr7-server`. It is immutable: each `with...()` gives a builder changing one thing.
  What a service doesn't give has a default:
  - `withLogger()`: where every part logs (default: nowhere);
  - `withContextKey()`: the key signing the context (`HmacContextSigner`); without it, or with
    `''`, the context isn't signed, and a warning is logged on each request;
  - `withRequestUrl()`: how the service's URLs are computed (default: the request's);
  - `withEmulator()`: where browsers are sent (default: the minipavi.fr emulator and gateway);
  - `withHandler()`: a `ConditionalRequestHandler` of the service (e.g. an API, a health
    check), tried before the framework's, in the order they are added;
  - `withMaxPageChanges()`: the page changes allowed in one request (default: 10, see below;
    0 forbids them; a negative number makes `build()` throw an `InvalidValueException`);
  - `withFont()`: the TrueType font of the HTML client's screen (default: the framework's
    Minitel font, `HtmlRenderer::MINITEL_FONT`); a file that can't be read throws an
    `InvalidValueException` there, rather than on each browser's request.

  A service needing another wiring builds the `Kernel` itself, from the classes below.

- **Lazy controllers:** a service with many screens shouldn't build every controller, and parse
  every page, on each request. With a dependency injection container such as PHP-DI, define
  the controllers as lazy: the router gets proxies, and a controller is only built when its
  route runs (PHP 8.4's native lazy objects, which keep controllers `final`). See the
  `config/container.php` of [the skeleton](https://github.com/remi-san/minipavi-service-skeleton):

  ```php
  $container = (new ContainerBuilder())->addDefinitions([
      'page.home' => factory([PageXmlParser::class, 'parseFile'])->parameter('file', "$config/pages/home.xml"),
      'controller.home' => create(HomeController::class)->constructor(get('page.home'))->lazy(),
      RouterBuilder::class => create()
          ->constructor(HomeController::NAME)
          ->method('addRoute', HomeController::NAME, get('controller.home')),
      Router::class => factory([RouterBuilder::class, 'build']),
  ])->build();
  $router = $container->get(Router::class);
  ```

- **`Kernel`** is what `public/index.php` runs, and the only place reading the globals: it
  creates the PSR-7 request from the globals with the creator it is given (e.g.
  `nyholm/psr7-server`'s `ServerRequestCreator`, with `nyholm/psr7`'s PSR-17 factories), has
  its handler (PSR-15) handle it, and sends the response with **`ResponseEmitter`** (its status,
  headers and body): every response, the gateway's included. No other class reads
  `$_SERVER`, the environment or the request's body, nor writes headers or output (what
  `MiniPaviCli` writes for the gateway is captured, below).
- **`WithUrls`** computes the service's URLs for the request once, with **`RequestUrl`**, and
  passes it on with them in its attributes (**`UrlAttribute`**): `Service`, the script, given
  to the gateway as the next URL to call (the client keeps it with the request it reads), and
  `Current`, the URL asked for, where the emulator sends browsers back. `RequestUrl` takes
  https when the request came over TLS (`HTTPS`, port 443), and its configuration explicitly
  (read from the environment in the service's container):
  - `SERVICE_URL` (e.g. `https://minitel.example.org`): scheme and host to use instead of the
    request's;
  - `TRUSTED_PROXIES` (e.g. `10.0.0.1, 172.16.0.0/12`): reverse proxies whose
    `X-Forwarded-Proto` is believed, when they terminate TLS. From anyone else, the header
    is ignored.
- **`Dispatch\Dispatcher`** decides who the HTTP request is for: the first of its
  **`ConditionalRequestHandler`**s matching the request (a list, tried in its order), or else
  its fallback. A `ConditionalRequestHandler` is
  a PSR-15 handler with a name and a condition (`RequestMatcher`); the framework's matchers are
  `FromMiniPaviGateway` (`User-Agent` with `MiniPAVI`) and `QueryParameterEquals` (e.g.
  `web=true`, that value only). The services send the gateway's requests to a `ServiceRunner`
  with `MiniPaviClient`, browsers asking **`?web=true`** to one with `HtmlClient`, and other
  browsers to the fallback, `EmulatorRedirect`. A service adds its own (e.g. an API, a health
  check) with its own matchers. The `Dispatcher` logs the handler of each request, at debug
  level.
- **`ServiceRunner`** runs the service for an HTTP request, through a client
  (`Gateway\Client`, in `Client\`) translating between HTTP and the service's values: the
  client reads the HTTP request into a **`Gateway\ClientExchange`** (`exchange()`), whose
  `request()` the service responds to, and whose `respond()` turns the `GatewayResponse` into the
  HTTP response. What the client needs to respond, besides what the service gets (the service's
  URL, the page `HtmlClient`'s browser showed), stays in the exchange. A request that can't be
  read gets an error as its response (see `Service`), through the client's `unreadable()` exchange;
  nothing to respond to (a disconnection, a background call of the
  gateway) is a `204`, and an
  response the client can't give a `500` (logged as critical).
  **The context is signed** on its way through the gateway (or the browser), so that the user
  can't change it: anyone could otherwise send another route, or other parameters.
  `Service` takes its **`Core\ContextSerializer`** (`KernelBuilder` gives it), which signs the
  context of each response and checks the one of each request it reads with a
  **`Core\Signing\ContextSigner`** (that of a connection, a disconnection or a
  background call isn't read, so it isn't checked). **`HmacContextSigner`** signs it with an
  HMAC-SHA256 of a secret key (`<signature>.<context>`), **`UnsignedContext`** doesn't. A context
  the signer didn't sign (`ForgedContextException`) is responded to as one that can't be read.
  Keep the key out of the repository: the services read it from the environment,
  **`CONTEXT_SECRET`** (`openssl rand -base64 32`), and don't sign the context without it, with
  a warning. Changing the key makes the users of sessions in progress start again at the
  default route. A serializer without signer (`new ContextSerializer($router)`, e.g. for a
  `ServiceTester`) doesn't sign it (`UnsignedContext`). Two clients:
  - **`MiniPaviClient`**, for the MiniPavi gateway, through `MiniPaviCli` used unchanged: it
    reads the gateway's request itself (background calls included, which the service doesn't
    respond to), and writes the response itself: `MiniPaviClient` captures it as the body of
    the HTTP response. It is the only class calling `MiniPaviCli`, besides `Vdt`'s Videotex
    helpers: its exchange and the commands it sends go through it;
  - **`HtmlClient`**, for a browser: it shows the service's screen as an HTML page
    (`Vdt\Screen\HtmlRenderer`), and lets the user navigate it as on a Minitel. The first request is a
    connection, at the default route, as the gateway's. The page is a form posting back to its URL,
    which `Client\HtmlForm` renders and reads back (`PostedForm`), `HtmlClient` turning it into
    a `GatewayRequest`, and a response into the page:
    - the response's **input field** (the `InputField` of its `InputCommand`: a text field, or a message of several lines) over the screen, at its position
      and size, one input per line;
    - the 8 **function keys** under the screen, in the Minitel's 2 × 4 layout, those the
      input doesn't accept (`validwith`) disabled; Enter in a field presses Envoi;
    - hidden, the context of the response, its input command (as JSON,
      `Client\InputCommandJson`), and the **screen so far**: a response drawing part of the
      screen (e.g. "Dernière page" on line 0) is drawn over it, as on a Minitel (kept from its
      last clear screen). The page's screen and command come back
      with the key as the client's state (`HtmlPageState`).

    A key that would **leave the service** (a response calling another service, a
    `ServiceCallCommand`, as `RunCommand::connectTo()` gives) is blocked: the page stays as it was, with
    `FrameworkMessage::WebLeave` on line 0. The key labels and that error are messages
    (`key.send`, `key.next`…, `web.leave`). The screen is drawn in the Minitel's colours, with
    double sizes, inverse video, underlining, blinking and mosaic pixels, as MiniPavi's
    emulator does, in self-contained HTML (inline CSS, no JavaScript). The text is drawn with the
    Minitel font of [Minitel-Canvas](https://github.com/Zigazou/Minitel-Canvas) (CC0, in
    `resources/fonts`), embedded in the page (`HtmlRenderer::MINITEL_FONT`, read for each
    page only: the gateway's requests don't need it).
- **`EmulatorRedirect`** sends other browsers to an emulator that opens the service
  (`EmulatorConfig`): with an emulator configured (`EMULATOR_URL`, the local stack), that
  emulator and the gateway `GATEWAY_URL`, which reaches the service at
  `SERVICES_INTERNAL_HOST`; otherwise (a public server), the minipavi.fr emulator and gateway,
  with the URL the browser asked for. `EmulatorConfig::fromValues()` takes those values, read
  from the environment in the service's container.
- **`Service`** responds to a request of the gateway, without I/O:
  `$service->handle(GatewayRequest): ?GatewayResponse` (null for a disconnection or a
  background call of the gateway, `BGCALL-WM-...`, which have nothing to respond to). It restores the `Context`, and runs the controller of the current route
  until a response responds to the gateway. `Service` owns the context: a response says, through
  a `Flow` (see below), the state and parameters its controller keeps and what comes next. A context without route
  starts at the default route. One whose route the service doesn't have (e.g. removed in a new
  version) starts again there too, without what the user typed, which was for a screen that
  doesn't exist any more (the serializer refuses it with an `UnknownRouteException`).
  More than 10 page changes (`ChangePage`) in one request are taken as controllers
  changing to each other endlessly, and responded to with an error; give another limit with
  `KernelBuilder::withMaxPageChanges(20)` (or `new Service(..., maxPageChanges: 20)`), 0 to
  forbid page changes.
  If serving a request fails (an exception or a PHP error, in a controller, a template, the
  context...), the error is logged, and the user gets a message on line 0
  (`FrameworkMessage::Error`, from the `$messages` of the constructor; see
  [Messages](#messages)): their next function key starts again at the default route.
- **`GatewayRequest`** is what the gateway sends: `key` (a `Minitel\Key`: a `FunctionKey` or a
  `ConnectionCode`, which its `functionKey()` and `connectionCode()` tell apart, or null for
  another key; `GatewayRequest::keyOf()` reads it from what the
  gateway sends, `fctn()` gives its name back), `content` (the text typed), `context` (given back from the previous response) and
  `terminal`. **`GatewayResponse`** is what is sent back: `vdt`, `context` and `command`, what
  the gateway does next (a `Gateway\Command`: a `WaitForKeyCommand`, the default, waiting for
  a key, then calling this service; an `InputCommand` reading a field; a `ServiceCallCommand`
  calling a service at once, with its URL and `CallMode`). Each client sends a command its own
  way, with its `Gateway\CommandSender`, whose method for it the command calls
  (`Command::sendWith()`). The commands are the framework's: a service sends them, but can't
  add one, since its clients wouldn't know it. The framework adds a command as a class
  implementing `Command` and a method of `CommandSender`, so that a client that doesn't
  send it can't be loaded. Both are plain values, so a
  service can be tested, or run elsewhere than PHP's web server, without `MiniPaviCli`.
- **Pages** are displayed for the user's terminal, which `<endkey>` depends on:
  `$page->display($request->terminal(), $data)`; display elements get it too
  (`Element::toVdt(Terminal $terminal): Vdt`).
- **Logging:** `Service`, `ServiceRunner`, `Dispatcher` and `EmulatorRedirect` take an optional PSR-3 logger (`KernelBuilder::withLogger()` gives it to all of them; e.g.
  Monolog, with its `ErrorLogHandler` for PHP's error log, as [the skeleton](https://github.com/remi-san/minipavi-service-skeleton) does) and log
  connections, disconnections and browsers sent to the emulator at info level, the handler
  of each request at debug level, and errors at error level (with the exception):
  `new Service($router, $messages, $serializer, logger: $logger)`, `new ServiceRunner($service, $client, $logger)`,
  `new Dispatcher($handlers, $fallback, $logger)`, `new EmulatorRedirect($emulator, $logger)`.
  Without one, nothing is logged.
- **A `Controller`** runs on a `Request` and returns a `Response`. The request gives:
  - `state()` and `parameters()`: what the controller's previous response asked to keep,
    from one request of the gateway to the next, typed as the controller declares them (see
    [Typed state and parameters](#typed-state)): a case of its `State` enum
    (`ControllerState`: `Init`, `Display`, `InitInput`, `ReadInput`, or its own), and an
    object of its `Parameters` class;
  - `functionKey()`: the function key the user pressed, a `FunctionKey` (`Send`, `Next`,
    `Previous`, `Refresh`, `Summary`, `Guide`, `Cancel`, `Correction`), or null for a
    connection code; `connectionCode()` the `Gateway\ConnectionCode` (`Connect`,
    `DirectConnect`, `Disconnect`, `BackgroundCall`), or null for a function key; `key()` gives
    either, as a `Minitel\Key` (`key()?->value` is the name the gateway sent: `ENVOI`…, or
    `CNX`, `FIN`…), null when there is none: after a `ChangePage`, at the default route
    started again because the context's route is unknown, and for what the gateway sends that
    isn't a known key (e.g. `DIRECT`, after a `CallMode::Direct` call);
  - `content()`: the text typed, one string per line of the input field, and
    `contentAsString()`;
  - `terminal()`: where the user connects from (`remoteAddr()`), and how: `isAdapter()` (an
    ESP32 or Minimit adapter, whose function keys need Shift) and `isTelnetOrPhone()`.

  Each response is built from its own class, in `Controller\Response\`:

  | Response | Effect | State kept by default |
  |---|---|---|
  | `new DisplayPage($vdt)` | sends Videotex content to the gateway; the next key comes back to this controller | `ControllerState::ReadInput` |
  | `RunCommand::readInput($vdt, $command)` | sends content and the command reading the page's input field (`Page::inputCommand()`, an `InputCommand`: the page's `Minitel\InputField`, where it is, its size and the keys validating it, and its prefill) to the gateway | `ControllerState::ReadInput` |
  | `new ChangePage($route, $parameters)` | switches to another controller, which runs at once: at `ControllerState::Init`, with `$parameters` (a `Parameters` object, or none), without the user's input; this controller is reset | `ControllerState::Init` |
  | `RunCommand::connectTo($url, $vdt)` | sends the user to another service, as a new connection (a `ServiceCallCommand` with `CallMode::Connect`) | `ControllerState::Init` |

  Every response but `ChangePage` responds to the gateway and ends the request. The state and
  parameters are what this controller keeps for its next run (for `connectTo()`, when the
  user comes back to it): a `State`, and a `Parameters` object (none by default). Give
  others after the content and the command (by name to give only one), e.g.
  `RunCommand::readInput($vdt, $command, parameters: new DirectoryParameters(2))`.
  `new RunCommand($vdt, $command, $state)` sends any other `Command`, keeping the state
  given (`null`: the initial state).
  `Response` doesn't know these classes: a response of your own (see below) is built the
  same way.
  They are readonly, and don't change the context. `Service` calls the response's
  `continueWith(Flow $flow): GatewayResponse`, where it says what comes next:
  `$flow->keep($state, $parameters)` keeps them for this controller,
  `$flow->respond($vdt, $command)` ends the request with that response to
  the gateway, and `$flow->goTo($route, $parameters)` resets this controller and runs
  another one, giving its gateway response. Every response but `ChangePage` extends
  `KeepingResponse`, which keeps the state and parameters it is built with, then calls its
  `next(Flow $flow)`. A response of your own does the same, without a change to `Service`:

  ```php
  final readonly class ClearAndShow extends KeepingResponse
  {
      public function __construct(private Vdt $vdt)
      {
          parent::__construct(ControllerState::ReadInput, null);
      }

      protected function next(Flow $flow): GatewayResponse
      {
          return $flow->respond(Vdt::clearScreen()->append($this->vdt));
      }
  }
  ```

- **`AbstractController`** runs the method of the request's state, so a controller only
  writes what each state does:

  ```php
  /** @extends AbstractController<NoParameters> */
  final class HomeController extends AbstractController
  {
      public const NAME = 'home';

      public function __construct(private Page $page) {}

      // What it keeps besides its state: nothing
      public function parametersType(): ParametersType
      {
          return ParametersType::none();
      }

      // Shows the screen (onInit() and onInitInput() do the same, unless overridden)
      protected function onDisplay(Request $request): Response
      {
          return RunCommand::readInput($this->page->display($request->terminal()), $this->page->inputCommand());
      }

      // Handles the key pressed and what was typed
      protected function onReadInput(Request $request): Response
      {
          if ($request->functionKey() === FunctionKey::Guide) {
              return new ChangePage('help');
          }

          return RunCommand::readInput(Vdt::line0('Unknown code'), $this->page->inputCommand());
      }
  }
  ```

  A state it doesn't know (e.g. kept by an older version of the service) starts it again.
  A controller with states of its own implements `Controller` with its own `State` enum.

- **`PaginatedController`** shows the items of a `PaginatedList` one page at a time: Suite
  and Retour change page, with "Première page" / "Dernière page" on line 0 at the ends
  (`FrameworkMessage::FirstPage`, `LastPage`: see [Messages](#messages)), Répétition redraws
  the page, and the page index is kept in `PageParameters`. The list gives the number of items
  and of items per page, the data of a page from its `Paginator`, and handles the other keys
  on the `ShownPage`, with `$page->display()`, `$page->stay($vdt)` (reads the next input on the
  page, after `$vdt`), `$page->next()` / `previous()` (the next or previous page, or the end
  message) and `$page->message()` (a message of the page):

  ```php
  final readonly class DirectoryList implements PaginatedList
  {
      public function __construct(private Directory $directory) {}

      public function itemCount(): int
      {
          return $this->directory->count();
      }

      public function itemsPerPage(): int
      {
          return 4;
      }

      // The template also gets the paginator's variables: {{page}}, {{pageCount}}, {{itemCount}}...
      public function pageData(Paginator $paginator): array
      {
          return ['services' => $this->directory->getServices($paginator->offset(), $paginator->limit())];
      }

      public function onOtherKey(Request $request, ShownPage $page): Response
      {
          return match ($request->functionKey()) {
              FunctionKey::Summary => new ChangePage('home'),
              FunctionKey::Send => $page->next(),
              default => $page->stay(),
          };
      }
  }

  // The route's controller
  new PaginatedController($page, new DirectoryList($directory));
  ```

- **`Paginator`** (`new Paginator($itemCount, $perPage, $index)`) is a page of `itemCount()`
  items, `limit()` at a time (immutable; there is always one page, empty without items, and an
  index out of the pages is kept within them):

  | Method | Gives |
  |---|---|
  | `offset()`, `limit()` | what to retrieve for the page (e.g. `LIMIT` / `OFFSET` of a query) |
  | `slice($items)` | the page's items, out of a list of all of them |
  | `index()`, `number()` | the page, from 0 (what `PageParameters` keeps) and from 1 (what the user sees) |
  | `itemCount()`, `pageCount()`, `itemCountOnPage()` | the number of items, of pages, and of items on this one |
  | `firstItem()`, `lastItem()` | the numbers of its first and last items, from 1 (0 without items) |
  | `isFirst()`, `isLast()`, `next()`, `previous()`, `at($index)` | where it is, and the pages around it (`next()` and `previous()`: null at the ends) |
  | `toArray()` | the variables of a template: `page`, `pageCount`, `itemCount`, `perPage`, `firstItem`, `lastItem` |

- <a id="typed-state"></a>**Typed state and parameters.** What a controller keeps is typed
  from end to end, and the static analysis checks it. A controller declares:
  - `initialState()`: a case of the string-backed enum of its states, implementing `State`
    (`ControllerState::Init` for an `AbstractController`);
  - `parametersType()`: the type of its parameters, a `ParametersType` of a class implementing
    `Parameters` (`ParametersType::of(DirectoryParameters::class)`, or `ParametersType::none()`
    for none). The class is immutable (readonly): the context keeps the object the controller
    is given, so changing it would change what the route keeps;

  and gets them typed: `Request<TState, TParameters>`, `$request->state()` and
  `$request->parameters()`. Its responses keep a case of its enum and an object of its class
  (null: the initial state, the default parameters); another type is an error.

  ```php
  final readonly class DirectoryParameters implements Parameters
  {
      public function __construct(public int $page = 0) {}

      // From what the context kept: a missing or invalid value gets its default
      public static function fromValues(Values $values): static
      {
          return new self(max(0, $values->int('page')));
      }

      public function toArray(): array
      {
          return ['page' => $this->page];
      }
  }

  enum SearchState: string implements State
  {
      case Init = 'init';
      case ReadQuery = 'read-query';
  }

  /** @implements Controller<SearchState, DirectoryParameters> */
  final class SearchController implements Controller
  {
      public function initialState(): SearchState
      {
          return SearchState::Init;
      }

      public function parametersType(): ParametersType
      {
          return ParametersType::of(DirectoryParameters::class);
      }

      public function run(Request $request): Response
      {
          $page = $request->parameters()->page;          // DirectoryParameters
          if ($request->state() === SearchState::ReadQuery) {
              // ...
          }

          return RunCommand::readInput($vdt, $command, SearchState::ReadQuery, new DirectoryParameters($page + 1));
      }
  }
  ```

  `Values` reads what the context kept (JSON, from a previous request, possibly of another
  version of the service, or forged): `int($name, $default)`, `string()`, `bool()` and
  `array()` coerce what is safe (`'12'` or `12.0` to `12`, a number to text, `1`/`0` and
  `'true'`/`'false'` to a bool), and give the default for anything else.

  Behind it, `RouterBuilder::addRoute()` binds each controller with its types (`Route`), in
  the immutable `Router` it builds, and the `Context` keeps a `RouteContext` per route.
  `Core\ContextSerializer` writes it as JSON (the enum's value and `toArray()`), and restores it
  with these types (a state the enum doesn't have is the initial one, routes kept that the
  router doesn't have are dropped, and a context whose current route it doesn't have is
  refused with an `UnknownRouteException`). `initialState()` and `parametersType()` are called for
  routes that don't run: they must not use the controller's properties, so that lazy
  controllers stay unbuilt.

- `Gateway\ConnectionCode` names the connection codes of `Request::key()` (`CNX`,
  `DIRECTCNX`, `FIN`, and the start of background calls, `BGCALL-WM-`): `ConnectionCode::of($key)`,
  `Request::connectionCode()`, or `GatewayRequest::$key`.

## Testing a service

`Testing\ServiceTester` scripts a session of a user against a `Service`, without the gateway,
in a PHPUnit test (PHPUnit is suggested by the framework, not required: add it to the
service's `require-dev`; without it, `ServiceTester`'s constructor says so). It runs the
service on requests built in memory, gives the context of each response back with the next
request, and plays every response since the connection on a simulated `Screen`, as the Minitel
keeps what isn't cleared:

```php
$serializer = new ContextSerializer($router);
$tester = new ServiceTester(new Service($router, $messages, $serializer), $serializer);   // optionally, a Terminal

$tester->connect()->assertRoute('home')->assertScreenContains('BIENVENUE')
    ->type('2')->press(FunctionKey::Send)->assertRoute('games')
    ->press(FunctionKey::Summary)->assertRoute('home');
```

- `connect()` starts a new session (an empty screen, no context), `type(...$lines)` gives
  the text of the input field for the next key (one string per line), `press(FunctionKey)`
  sends a key, and `disconnect()` checks a disconnection gets no response.
- `assertScreenContains()`, `assertScreenNotContains()`, `assertLineContains($row, ...)` and
  `assertLine0()` check the screen as text, accented characters included
  (`Screen::text()`); `assertRoute()` the route the user's next key goes to, and
  `assertConnectsTo($url)` a connection to another service (`RunCommand::connectTo()`). A
  failed assertion shows the screen.
- `screen()`, `response()` (the last `GatewayResponse`) and `context()` give the rest, for
  other assertions.

To test the service as it is served, build its `Service` from the same wiring as its
`public/index.php`: [the skeleton](https://github.com/remi-san/minipavi-service-skeleton) keeps it in `config/container.php`, which
its `public/index.php` and its `tests/` both require.

## Messages

Text shown on the Minitel is not written in the code: it is read from messages files, by name.

- **Names** are enum cases implementing `Message\Message`, whose value is the name in the
  files: `FrameworkMessage` for the framework's (`error`, `page.first`, `page.last`,
  `endkey`, `endkey.shift`, and for the HTML view the key labels `key.*` and `web.leave`), and an enum of its own for a service's.
- **Texts** come from `config/messages.xml` in the framework (its defaults,
  `MessagesXmlParser::defaults()`, read where the service is built), then the service's
  messages file over them, then a page's
  `<message>` over those, for that page. A text can have `{{placeholders}}`, as in page
  templates: `{{name}}`, or a dotted path through arrays and public object properties
  (`{{user.name}}`).
- **Always given:** `Service`, `PageXmlParser` and the pages take the messages as a required
  argument, never a hidden default: pass `(new MessagesXmlParser())->defaults()` for the
  framework's only.

```xml
<messages xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="../vendor/remi-san/minipavi-service/schema/messages.xsd">
  <message name="service.unknown">Service inexistant</message>
  <message name="connected.from">Connexion de {{remoteAddr}}</message>
  <message name="page.last">Fin de la liste</message>   <!-- redefines the framework's -->
</messages>
```

```php
enum AccMessage: string implements Message
{
    case UnknownService = 'service.unknown';
    case ConnectedFrom = 'connected.from';
}

// Composition root: read and checked at startup (every message of these enums has a text)
$parser = new MessagesXmlParser();
$messages = $parser
    ->parseFile("$config/messages.xml", $parser->defaults())
    ->check(FrameworkMessage::class, AccMessage::class);
$parser = new PageXmlParser($messages);              // pages redefine them with <message>
$service = new Service($router, $messages, new ContextSerializer($router), logger: $logger);  // for its error message

// In a controller: the page's text, its placeholders replaced
Vdt::line0($this->page->message(AccMessage::ConnectedFrom, ['remoteAddr' => $addr]));
```

A message without text, a placeholder without its variable or whose value isn't text (an
array, an object without `__toString()`), or a `{{...}}` that isn't a placeholder (`{{1}}`,
`{{user-name}}`: a page template would leave it as it is) is a `LogicException` naming the
message; a messages file that can't be read is a `MessagesException` with the file and line.
`schema/messages.xsd` describes messages files.

## Page XML format

A page file has a `<page>` root, read by `PageXmlParser`. Errors throw a `PageXmlException`
giving the file and line. They are found when the page is parsed (`minipavi lint` parses every
page, see [Command line](#command-line)): the elements of a static page, and those of a
template without placeholders, are built then (a missing `.vdt` file, an unknown color...).
Only a template's elements with placeholders are built when displayed, and their errors throw
a `TemplateException`, with the file and line too; in a `<foreach>`, so is an element's `row`. The screen is 40 columns × 24 rows (row 0 is the status line), and
elements are drawn in order:

| Element | Draws |
|---|---|
| `<background>file.vdt</background>` | Videotex page from the page's folder or one of its subfolders (e.g. `images/logo.vdt`), sent as is (e.g. made with a Videotex editor). Put it first to draw over it. |
| `<image row col>file.vdt</image>` | Videotex picture from the page's folder or one of its subfolders, drawn with its top-left cell (row 1, column 1 of the file) at `row`, `col` (default 1, 1): what goes past the screen is cut, and it doesn't clear the screen. In a template, the file name can be a placeholder (`{{game.image}}`), and in a `<foreach>`, `row` is relative to the item, to show a picture per item |
| `<text row col align="center" color bg size fill="yes">…</text>` | text; `size`: normal, double-height, double-width, double; `fill="yes"` paints the rest of the row with `bg` |
| `<line row col length char color/>` | horizontal line; `char`: top (`_`), middle (`` ` ``), bottom (`~`) or any character |
| `<key row col color bg>Envoi</key>` | function key badge, in reverse video (`bg`: around it) |
| `<endkey row color/>` | "Connexion/fin" hint, centered, with "[Maj] +" for ESP32/Minimit adapters (messages `endkey`, `endkey.shift`) |
| `<input row col length lines validwith/>` | input field read by `Page::inputCommand()` (default: row 13, 2 lines, to the end of the row) |

- `validwith`: keys validating the input, separated by spaces (default `envoi`): envoi, sommaire,
  annulation, retour, repetition, guide, correction, suite (`FunctionKey::xmlName()`). A one-line field is a text input
  where every key validates; with several lines, Suite and Retour move between them.
- Colors: black, red, green, yellow, blue, magenta, cyan, white. `col` defaults to 1.

`<page>` can also set up the Minitel, sent by `Page::display()` before the content (a setting
left out is left as it is):

| Attribute | Values | Sets |
|---|---|---|
| `keyboard` | `uppercase`, `lowercase` | the case of the letters typed (the Minitel starts in uppercase) |
| `echo` | `on`, `off` | whether the Minitel shows what is typed itself: `off` when the gateway's input commands show it |
| `scroll` | `on`, `off` | whether the screen scrolls up past the last row: `off` for page mode |

e.g. `<page type="static" keyboard="lowercase" echo="off" scroll="off">`, for a screen
reading what the user types.

At its top level, `<message name="page.last">Fin de l'article</message>` redefines a message
of the service for this page (see [Messages](#messages)).

`<page type="static">` is drawn as is. `<page type="template">` can also have placeholders and
instructions, filled in with the data given to `Page::display($terminal, $data)`:

| Syntax | Meaning |
|---|---|
| `{{name}}`, `{{service.code}}` | placeholder, in any text or attribute (not in `<input>`); dotted paths go through arrays and public object properties (or those an object gives through `__isset()` and `__get()`): any other is an unknown variable |
| `<foreach items="services" as="service" row="5" step="2">…</foreach>` | repeats its content for each item, `step` rows apart. Inside, rows are relative to the item (0 = its first row), and `loop.index` (from 1), `loop.count`, `loop.first` and `loop.last` are available |
| `<if test="loop.last">…</if>` | content shown only if the variable is set and not empty |
| `<if test="!loop.last">…</if>` | … or only if it is not |

### Elements of a service

A service adds its own display elements by giving `PageXmlParser` a `Page\Parser\PageElements`
after its messages. For each page, `builders()` gets the files of the page's folder
(`PageFiles`) and the page's messages, and returns an `ElementBuilder` per element: its tag,
how it is built from its attributes and text (placeholders already replaced), whether its
`row` is relative inside a `<foreach>`, and whether it can be in one.

```php
final readonly class GameElements implements PageElements
{
    public function builders(PageFiles $files, Messages $messages): array
    {
        return [new ScoreBuilder()];   // <score row="3" value="{{game.score}}"/>
    }
}

$parser = new PageXmlParser($messages, new GameElements());
// With PHP-DI: PageXmlParser::class => create()->constructor(get(Messages::class), get(GameElements::class))
```

The parser takes one `PageElements`: a service with several sets of elements returns all their
builders from one.

A builder reports what is wrong in the page with a `PageXmlException` or an
`InvalidValueException`, as `Position`, `Span`, `InputField` and `PageFiles::read()` do: the
parser gives it the file and line, as for the framework's elements. Any other exception is a
bug of the builder, and goes through as it is, as does an exception of a variable's own code
(its `__get()`, `__toString()`...) read by a template or a message.

A tag of the format (`text`, `foreach`, `input`...) can't be taken again, nor one tag by two
elements: building the `PageXmlParser` then throws a `LogicException`, at boot. For this check,
`builders()` is also called once with a placeholder folder: building the builders must not
read the files. The keys of the array it returns are ignored. These elements aren't in the XML
Schema, which rejects them, and `minipavi lint` and `minipavi preview` only know the
framework's.

### XML Schema

`schema/page.xsd` describes the format as an XML Schema (1.0), for editors to complete and
check pages as they are written. A page points to it with `xsi:noNamespaceSchemaLocation`,
relative to the page, e.g. from a service's `config/pages/`:

```xml
<page type="template"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="../../vendor/remi-san/minipavi-service/schema/page.xsd">
```

`xmllint --noout --schema vendor/remi-san/minipavi-service/schema/page.xsd config/pages/*.xml`
checks pages from the command line. The parser remains the reference: the schema can't
express some of its rules, listed at the top of `page.xsd` (e.g. `<foreach>` or a
placeholder in a static page, or a second `<input>`), and refuses unknown attributes, which
the parser ignores. `tests/Page/Parser/PageXsdTest.php` keeps the two in agreement: when the
format changes, change `page.xsd` with the parser.

## Videotex content

Videotex goes through the framework as a **`Vdt` value**, never as a string: pages display
one (`Page::display()`), responses and `Flow::respond()` take one, and `GatewayResponse` holds
one. Only the gateway client (`MiniPaviClient::send()`) turns it into bytes. Text and
Videotex can't be mixed up:

```php
Vdt::text('Déjà vu');                       // text: its accents encoded in G2
new Vdt("\x0C");                            // raw Videotex
Vdt::line0('Choix inconnu');               // on the status line (MiniPaviCli's writeLine0())
$page->display($terminal)->append(Vdt::line0('Bienvenue'));  // one after the other
Vdt::join($a, $b, $c);
```

A `Vdt` keeps its bytes, and reads them only to play, show or move them (below).

Codes to send as they are have names: `Vdt\Videotex` for Videotex (`CLEAR_LINE`,
`CURSOR_OFF`, `INVERSE_ON`, sizes, `RESET_CHARSETS`..., and the colors of a
`Minitel\Color`: `Videotex::textColor($color)` and `backgroundColor($color)`), and the `Minitel\Keyboard`,
`Minitel\LocalEcho` and `Minitel\Scroll` enums for the sequences configuring the Minitel
(`Keyboard::Lowercase->sequence()`...). A page sends them itself, from the attributes of
`<page>` (see [Page XML format](#page-xml-format)); `Page\Setup` sends them for other content.

They replace MiniPaviCli's global `VDT_*` and `PRO_*` constants, which are only defined once
`MiniPaviCli` is loaded. `Vdt` holds Videotex content, its bytes, and joins it (`join()`,
`append()`). Its constructors encode as MiniPaviCli's Videotex helpers do, without calling
them: `Vdt::text()` (as `toG2()`), `Vdt::position($position)` (a `Minitel\Position`,
`setPos()`), `Vdt::line0()` (`writeLine0()`), `Vdt::clearScreen()` and
`Vdt::repeat($char, $count)` (`repeatChar()`).

Other classes read Videotex content, e.g. of a `.vdt` file: `Screen::of()` draws it, `Offset`
moves it on the screen, and `ClearScreens` works on its clear screens; `<image>` uses them.

```php
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;

$picture = new Vdt(file_get_contents('picture.vdt'));
$moved = (new Offset(8, -2))->apply($picture);   // 8 columns right, 2 rows up (negative: left, up)
echo $moved->toString();                         // the Videotex content
echo Screen::of($moved)->preview();              // the screen, as text
```

- **`(new Offset($x, $y))->apply($vdt)`**: Videotex positions are mostly absolute, but text also flows from cell
  to cell and wraps at column 40, so the content is played on a simulated screen (`Vdt\Player`) rather than
  having its positions patched. Characters that end up off the screen are dropped instead of
  wrapping onto the next row, and the cursor is positioned again where needed, with the
  current attributes (character set, colors, size...), which a positioning resets. Row 0,
  the status line, is not moved. A double-height or double-size character moved onto row 1,
  where the Minitel discards `ESC M` and `ESC O`, is written without them: at normal size, or
  double width for double size, in the columns it had. Text keeps its zone (see `Screen::of()`):
  each delimiter is written with `ESC Z` or `ESC Y` before it, and a space that would
  otherwise start another zone (a background colour sent again waits for it) or lose its own
  (its delimiter went off the screen) is written as a delimiter of its zone. That space isn't
  underlined, as a delimiter isn't. Text moved to column 1 before such a space has no column
  left for its delimiter, and loses its zone.
- **`ClearScreens`**: `removed($vdt)` is the same content without its clear screens (FF), to
  draw it over other content, and `fromLast($vdt)` the content from its last one, what still
  shows.
- **`HtmlRenderer::render($screen, $overlay)`**: the screen in HTML (a `<div class="minitel">`,
  with `$overlay` over it), `style()` its CSS (the cell size in `--w`, `--h`, the Minitel font
  in `--font`) with the font embedded, and `place($position, $length)` the style placing an
  element over a cell, with all of its geometry (position, size, its border included, and
  stacking above double-height cells), so the element's own style only says how it looks (it
  is inline, so it takes over the element's own `position`, `height`, `box-sizing` and
  `z-index`); the page around it is the HTML client's (`Client\HtmlForm`, see
  `HtmlClient` above).
- **`Screen::of($vdt)`**: the `Screen` drawn by the content: a `Cell` (character and attributes) or
  `null` per row and column. `line($row)` and `text()` give the text of a row and of the
  screen, G2 characters decoded (`é`, `£`, `½`, `Œ`...; `Cell::text()`), and `preview()`
  the same rows framed and numbered, to read in a terminal. As on a Minitel, `ESC M` (double
  height) and `ESC O` (double size) received on rows 0 and 1 are discarded, the size staying
  as it was; a double height already set when a cursor move (LF, VT) brings the cursor onto
  row 1 is kept, the character drawn over row 0. A tall text character (double height or
  double size) wrapping at the end of a row goes 2 rows down, cycling through rows 1 to 24
  (24 to 2, 23 to 1), and the text carries on along that row; a move right (HT) from column
  40 goes 1 row down, as BS, LF and VT move 1 row. SO and SI set the size back to normal
  and turn off inverse video and separated mosaic, SO also underline; a size (`ESC L` to
  `ESC O`) or inverse video (`ESC ]`) received in mosaic is discarded: mosaic characters are
  never tall, wide or inverse. Underline in text is a zone attribute: `ESC Z` and `ESC Y`
  wait for a delimiter, a space drawn while a zone attribute (underline, background colour,
  masking) waits. The delimiter isn't underlined, and starts a zone running along its row up
  to the next delimiter, mosaic or empty cell, over what is already there too: write
  `ESC Z`, a space, then the text (as `Page\Display\Key` does). A positioning drops what waits,
  and SO ends the zone. CAN clears with spaces, so while a zone attribute waits, the first one
  is a delimiter. A mosaic character uses up nothing that waits. Background colour and masking
  are still applied at once.
- **`Vdt\Player::play($vdt, $listener)`** (internal, what `Screen::of()` and `Offset` are
  built on): plays the content on the simulated screen, telling a `Vdt\ScreenListener` each
  character (`glyph()`, with its row, column, width and attributes), each delimiter
  (`delimiter()`, with the `Screen\Zone` it starts), each CAN (`clearLine()`, with the zone
  its first space starts if it is a delimiter) and the other `Token`s (`sequence()`, with a
  copy of the cursor). `Screen\ScreenCells::of()` collects the cells of
  `Screen::of()`, `Vdt\OffsetWriter::write()` writes the content moved by `Offset`: each listens
  to one content, built by that static method only.

`bin/minipavi vdt-offset` does the same from the command line (see [Command line](#command-line)).

## Command line

`bin/minipavi` is the framework's command line, built with `symfony/console`: Composer installs
it in a service as `vendor/bin/minipavi` (`minipavi list` lists its commands, `minipavi help
<command>` describes one). The commands reading pages take
the service's messages with `--messages`, as the service reads them (a page's `<message>`
must be one of them); without it, the framework's only.

```sh
vendor/bin/minipavi lint --messages config/messages.xml config         # every page, for CI
vendor/bin/minipavi preview config/pages/games.xml games.json          # the screen of a page
vendor/bin/minipavi vdt-offset --preview -- images/background.vdt 8 -2 # move a Videotex page
```

- **`lint [--messages <messages.xml>] <path>...`** parses every page (XML file whose root is
  `<page>`) of the files and folders given, with their subfolders, as the service does: other
  XML files are skipped. Each error is printed with its file and line, and the command exits
  with 1 on an error, or if it found no page. [The skeleton](https://github.com/remi-san/minipavi-service-skeleton) runs it with
  `composer lint`, in CI.
- **`preview [--messages <messages.xml>] <page.xml> [<data.json>]`** prints the screen a page
  draws, as text (`Screen::preview()`): a template with the variables of a JSON object (e.g.
  `{"games": [{"name": "Rocket Rescue"}]}`).
- **`vdt-offset [--preview] <input.vdt> <x> <y> [<output.vdt>]`** moves the content of a
  Videotex page by `x` columns and `y` rows (negative: left, up), with `Offset` (see
  [Videotex content](#videotex-content)): to the output file, or the standard output; input `-`
  reads the standard input. `--preview` prints the screen as text, before and after. A
  negative number would be read as an option: put `--` before the arguments
  (`vdt-offset -- background.vdt 8 -2`).

Exit codes: 0 on success, 1 when the command fails (an error in a page, a file it can't read
or write, a wrong command line). The commands are Symfony `Command`s, in `Cli\`.

## Developing the framework

The code is checked with [Mago](https://mago.carthage.software) (`mago.toml`): its formatter
(PER Coding Style, 120 columns, one import per `use`), and its linter and static analyzer,
which fail on any issue, notes included. `mago.toml` says why each rule turned off or ignored
is, the ignored ones only in the files where they occur. Issues known when a rule was turned
on are in `lint-baseline.toml`: not reported, but the linter fails on new ones, and on an entry
whose issue was fixed (regenerate it with `vendor/bin/mago lint --generate-baseline`).
`stubs/MiniPaviCli.php` gives the
types `MiniPaviCli` doesn't declare (a patch of the library's class). Every file has
`declare(strict_types=1)`.

```sh
composer install    # once, in this folder: development tools in vendor/
composer cs         # checks the formatting
composer cs-fix     # formats the code
composer analyse    # linter and static analysis (mago lint, mago analyze)
composer test       # tests (PHPUnit, tests/)
composer coverage   # tests with coverage, as text and in coverage.xml (needs pcov or Xdebug)
```

Tests are in `tests/`, with their files in `tests/fixtures/`. Pages are checked through the
screen they draw (`Screen::preview()`). `Offset` is checked in two ways: against the
property that the moved screen is the original one moved, and byte for byte against golden
files (`tests/fixtures/vdt/*.offset_*.vdt`). Run `UPDATE_GOLDEN=1 composer test` to rewrite
the golden files after an intended change.

A new display element of the framework is a `Page\Parser\Element\ElementBuilder` (see
[Elements of a service](#elements-of-a-service)), added to `StandardElements`; then the XML
Schema (`schema/page.xsd`) and the [Page XML format](#page-xml-format) above.
The other tags (`<input>`, `<foreach>`, `<if>`, `<message>`) each have their
`Page\Parser\Tag\TagParser`, by tag in `NodesParser::standard()`, which reads the children
of an element with them, and gives the nodes and the page's `<input>` (`ParsedNodes`).

GitHub Actions runs the three checks on PHP 8.2, 8.3, 8.4 and 8.5 for every change to the
framework.
