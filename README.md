# MiniPavi service framework

PHP framework for Minitel services behind a [MiniPavi](https://www.minipavi.fr/) gateway. It builds on the
official [`ludosevilla/minipavi-cli`](https://packagist.org/packages/ludosevilla/minipavi-cli).

- **Pages** described in XML: static, or templates with placeholders, loops and conditions
- **Controllers**, one per screen, each a small state machine returning **responses**
- A **router** and a **context** keeping each controller's state between requests
- A **service without I/O**: it answers a `GatewayRequest` with a `GatewayResponse`, which
  `MiniPaviClient` reads from and sends to the gateway, through the static `MiniPaviCli`
- **`Vdt`**, to read Videotex content (e.g. pictures made with a Videotex editor) and move it on the screen

Requires PHP 8.2.

The code (namespace `MiniPaviService`, in `src/`) is organized by layer. The runtime uses the
controllers, which use the pages, and none uses those above it: `Core\` → `Controller\` →
`Page\` → `Message\`, `Minitel\`, `Vdt\`, and the clients and the entry point sit on top:
`Client\` → `Http\` → `Core\` (`Gateway\`'s requests and answers are values all of them use,
which depend on nothing above them):

| Namespace | Holds |
|---|---|
| `MiniPaviService` | the `Controller` and `Page` interfaces |
| `Core\` | the runtime: `Service`, `Router` and its `Route`s, `Context` and its `RouteContext`s, `RequestFlow` |
| `Controller\` | `AbstractController`, `Request`, `Response` and the response types, `Flow`, the states (`State`, `ControllerState`) |
| `Controller\Parameters\` | what a controller keeps: `Parameters`, `NoParameters`, and the raw `Values` they are read from |
| `Controller\Pagination\` | `PaginatedController`, `Paginator`, `PageParameters` |
| `Page\` | static and template pages, their display elements, and the page XML parser |
| `Message\` | the messages shown on screen: `Message`, `FrameworkMessage`, `Messages` and their XML parser |
| `Minitel\` | the user's terminal: `Terminal`, its `FunctionKey`s, its settings (`Keyboard`, `LocalEcho`, `Scroll`), and the `InputField` where the user types |
| `Vdt\` | Videotex: codes, reading, writing and moving Videotex content (`Vdt`, `Videotex`); in `Screen\`, the Minitel screen it draws (`Screen`, `Cell`) and its HTML rendering (`HtmlRenderer`) |
| `Gateway\` | the requests and answers the service handles (`GatewayRequest`, `GatewayResponse`, `CallMode`, the `InputCommand` reading a field), and the `Client` interface reading and sending them |
| `Client\` | the clients: `MiniPaviClient` for the MiniPavi gateway, `HtmlClient` for a browser (`?web=true`) |
| `Http\` | the entry point for the web server, on PSR-7 / PSR-15: `Kernel`, `ResponseEmitter`, `ServiceRunner`; in `Dispatch\`, who handles the request (`Dispatcher`, `ConditionalRequestHandler`, `RequestMatcher`s); in `Url\`, the request's URLs (`RequestUrl`, `UrlAttribute`, `WithUrls`); in `Emulator\`, the emulator browsers are sent to (`EmulatorConfig`, `EmulatorRedirect`) |
| `Testing\` | `ServiceTester`, functional tests of a service (needs PHPUnit) |

## Installing

From a service in this repository, as a `path` repository (Composer symlinks it into `vendor/`,
so edits to the framework apply without reinstalling):

```json
{
    "repositories": [{ "type": "path", "url": "../../framework" }],
    "require": { "remi-san/minipavi-service": "*@dev" }
}
```

In Docker, the framework and the service keep the repository's layout (`/app/framework` and
`/app/services/<service>`), so that the relative path and the symlink resolve there too.

## Writing a service

`public/index.php` builds the pages and controllers, and hands them to the service:

```php
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use MiniPaviService\Core\Router;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\KernelBuilder;
use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\Message\Messages;
use MiniPaviService\Page\Parser\PageXmlParser;

require __DIR__.'/../vendor/autoload.php';

// The framework's messages; a service usually reads its own over them (see Messages)
$messages = Messages::defaults();

$router = new Router();
$router->addRoute(HomeController::NAME, new HomeController((new PageXmlParser($messages))->parseFile(__DIR__.'/../config/pages/home.xml')));
$router->setDefaultRoute(HomeController::NAME);

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
    check), tried before the framework's, in the order they are added.

  A service needing another wiring builds the `Kernel` itself, from the classes below.

- **Lazy controllers:** a service with many screens shouldn't build every controller, and parse
  every page, on each request. With a dependency injection container such as PHP-DI, define
  the controllers as lazy: the router gets proxies, and a controller is only built when its
  route runs (PHP 8.4's native lazy objects, which keep controllers `final`). See
  `services/*/config/container.php`:

  ```php
  $container = (new ContainerBuilder())->addDefinitions([
      'page.home' => factory([PageXmlParser::class, 'parseFile'])->parameter('file', "$config/pages/home.xml"),
      'controller.home' => create(HomeController::class)->constructor(get('page.home'))->lazy(),
      Router::class => create()
          ->method('addRoute', HomeController::NAME, get('controller.home'))
          ->method('setDefaultRoute', HomeController::NAME),
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
  to the gateway as the next URL to call (it travels in `GatewayRequest::$serviceUrl`), and
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
  client turns the HTTP request into a `GatewayRequest`, the service answers it, and the client
  turns the `GatewayResponse` into the HTTP response. What the client needs to answer, besides what the service
  gets, is its state (`Gateway\ClientState`): read with the HTTP request, kept in the
  `GatewayRequest` (`$clientState`), and given back to the client with it. A client without
  any uses the null object `NoClientState`, as requests built in memory do. A request that can't be read is answered
  as an error (see `Service`); nothing to answer (a disconnection, a background call of the
  gateway) is a `204`, and an
  answer the client can't give a `500` (logged as critical).
  **The context is signed** on its way through the gateway (or the browser), so that the user
  can't change it: anyone could otherwise send another route, or other parameters.
  `ServiceRunner` takes a **`Core\Signing\ContextSigner`**: it checks the context of the
  request before the service gets it (`GatewayRequest::verify()`; the context of a connection,
  a disconnection or a background call isn't read, so it isn't checked), and signs the context of the answer before
  the client gets it (`GatewayResponse::sign()`). **`HmacContextSigner`** signs it with an
  HMAC-SHA256 of a secret key (`<signature>.<context>`), **`UnsignedContext`** doesn't. A context
  the signer didn't sign (`ForgedContextException`) is answered as one that can't be read.
  Keep the key out of the repository: the services read it from the environment,
  **`CONTEXT_SECRET`** (`openssl rand -base64 32`), and don't sign the context without it, with
  a warning. Changing the key makes the users of sessions in progress start again at the
  default route. `Service` and `ServiceTester` deal with the context unsigned. Two clients:
  - **`MiniPaviClient`**, for the MiniPavi gateway, through `MiniPaviCli` used unchanged: it
    reads the gateway's request itself (background calls included, which the service doesn't
    answer), and writes the answer itself: `MiniPaviClient` captures it as the body of the
    HTTP response;
  - **`HtmlClient`**, for a browser: it shows the service's screen as an HTML page
    (`Vdt\Screen\HtmlRenderer`), and lets the user navigate it as on a Minitel. The first request is a
    connection, at the default route, as the gateway's. The page is a form posting back to its URL:
    - the answer's **input field** (the `InputField` of its `InputCommand`: a text field, or a message of several lines) over the screen, at its position
      and size, one input per line;
    - the 8 **function keys** under the screen, in the Minitel's 2 × 4 layout, those the
      input doesn't accept (`validwith`) disabled; Enter in a field presses Envoi;
    - hidden, the context of the answer, its input command, and the **screen so far**: an
      answer drawing part of the screen (e.g. "Dernière page" on line 0) is drawn over it, as
      on a Minitel (kept from its last clear screen). The page's screen and command come back
      with the key as the client's state (`HtmlPageState`).

    A key that would **leave the service** (an answer whose next URL is another one, as
    `Response::connectTo()`) is blocked: the page stays as it was, with
    `FrameworkMessage::WebLeave` on line 0. The key labels and that error are messages
    (`key.send`, `key.next`…, `web.leave`). The screen is drawn in the Minitel's colours, with
    double sizes, inverse video, underlining, blinking and mosaic pixels, as MiniPavi's
    emulator does, in self-contained HTML (inline CSS, no JavaScript). The text is drawn with the
    Minitel font of [Minitel-Canvas](https://github.com/Zigazou/Minitel-Canvas) (CC0, in
    `resources/fonts`), embedded in the page (`HtmlRenderer::MINITEL_FONT`).
- **`EmulatorRedirect`** sends other browsers to an emulator that opens the service
  (`EmulatorConfig`): with an emulator configured (`EMULATOR_URL`, the local stack), that
  emulator and the gateway `GATEWAY_URL`, which reaches the service at
  `SERVICES_INTERNAL_HOST`; otherwise (a public server), the minipavi.fr emulator and gateway,
  with the URL the browser asked for. `EmulatorConfig::fromValues()` takes those values, read
  from the environment in the service's container.
- **`Service`** answers a request of the gateway, without I/O:
  `$service->handle(GatewayRequest): ?GatewayResponse` (null for a disconnection or a
  background call of the gateway, `BGCALL-WM-...`, which have nothing to answer). It restores the `Context`, and runs the controller of the current route
  until a response answers the gateway. `Service` owns the context: it keeps the state and
  parameters a response gives for its controller, then the response says what comes next,
  through a `Flow` (see below). A context without route, or
  whose route the service doesn't have (e.g. removed in a new version), starts again at the
  default route.
  If serving a request fails (an exception or a PHP error, in a controller, a template, the
  context...), the error is logged, and the user gets a message on line 0
  (`FrameworkMessage::Error`, from the `$messages` of the constructor; see
  [Messages](#messages)): their next function key starts again at the default route.
- **`GatewayRequest`** is what the gateway sends: `key` (a `Minitel\Key`: a `FunctionKey` or a
  `ConnectionCode`, or null for another key; `GatewayRequest::keyOf()` reads it from what the
  gateway sends, `fctn()` gives its name back), `content` (the text typed), `context` (given back from the previous answer) and
  `terminal`. **`GatewayResponse`** is what is sent back: `vdt`, `context`, `next` (the URL the
  gateway calls next, null for this service's), `command` (e.g. an input command),
  `directCall` (a `CallMode`, to call `next` at once) and `echo`. Both are plain values, so a
  service can be tested, or run elsewhere than PHP's web server, without `MiniPaviCli`.
- **Pages** are displayed for the user's terminal, which `<endkey>` depends on:
  `$page->display($request->terminal(), $data)`; display elements get it too
  (`Element::getContent(Terminal $terminal)`).
- **Logging:** `Service`, `ServiceRunner`, `Dispatcher` and `EmulatorRedirect` take an optional PSR-3 logger (`KernelBuilder::withLogger()` gives it to all of them; e.g.
  Monolog, with its `ErrorLogHandler` for PHP's error log, as the services of this repository do) and log
  connections, disconnections and browsers sent to the emulator at info level, the handler
  of each request at debug level, and errors at error level (with the exception):
  `new Service($router, $messages, $logger)`, `new ServiceRunner($service, $client, $signer, $logger)`,
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
    the name the gateway sent (`ENVOI`…, or `CNX`, `FIN`…);
  - `content()`: the text typed, one string per line of the input field, and
    `contentAsString()`;
  - `terminal()`: where the user connects from (`remoteAddr()`, `typeSocket()`,
    `versionMinitel()`), and how: `isAdapter()` (an ESP32 or Minimit adapter, whose function
    keys need Shift) and `isTelnetOrPhone()`.

  `Response`'s named constructors build the responses:

  | Response | Effect | State kept by default |
  |---|---|---|
  | `Response::display($vdt)` | sends Videotex content to the gateway; the next key comes back to this controller | `ControllerState::ReadInput` |
  | `Response::readInput($vdt, $command)` | sends content and the command reading the page's input field (`Page::inputCommand()`, an `InputCommand`: the page's `Minitel\InputField`, where it is, its size and the keys validating it, and its prefill) to the gateway | `ControllerState::ReadInput` |
  | `Response::goTo($route, $parameters)` | switches to another controller, which runs at once: at `ControllerState::Init`, with `$parameters` (a `Parameters` object, or none), without the user's input; this controller is reset | `ControllerState::Init` |
  | `Response::connectTo($url, $vdt)` | sends the user to another service, as a new connection (`CallMode::Connect`) | `ControllerState::Init` |

  Every response but `goTo()` answers the gateway and ends the request. The state and
  parameters are what this controller keeps for its next run (for `connectTo()`, when the
  user comes back to it): a `State`, and a `Parameters` object (none by default). Give
  others by name, e.g. `Response::readInput($vdt, $command, parameters: new
  DirectoryParameters(2))`. The classes behind them
  (`DisplayPage`, `RunCommand`, `ChangePage`, `RunExternalService`) can also be built directly.
  They are readonly, and don't change the context. Once `Service` has kept a response's
  state and parameters, it calls `continueWith(Flow $flow): GatewayResponse`, where the
  response says what comes next: `$flow->answer($vdt, $command, $next, $directCall)` ends
  the request with that answer to the gateway, and `$flow->goTo($route, $parameters)` runs
  another controller and gives its answer. A response of your own extends `Response` and
  implements `continueWith()`, without a change to `Service`:

  ```php
  final readonly class ClearAndShow extends Response
  {
      public function __construct(private Vdt $vdt)
      {
          parent::__construct(ControllerState::ReadInput, null);
      }

      public function continueWith(Flow $flow): GatewayResponse
      {
          return $flow->answer(Vdt::clearScreen()->append($this->vdt));
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
      public function parametersType(): string
      {
          return NoParameters::class;
      }

      // Shows the screen (onInit() and onInitInput() do the same, unless overridden)
      protected function onDisplay(Request $request): Response
      {
          return Response::readInput($this->page->display($request->terminal()), $this->page->inputCommand());
      }

      // Handles the key pressed and what was typed
      protected function onReadInput(Request $request): Response
      {
          if ($request->functionKey() === FunctionKey::Guide) {
              return Response::goTo('help');
          }

          return Response::readInput(Vdt::line0('Unknown code'), $this->page->inputCommand());
      }
  }
  ```

  A state it doesn't know (e.g. kept by an older version of the service) starts it again.
  A controller with states of its own implements `Controller` with its own `State` enum.

- **`PaginatedController`** shows items one page at a time: Suite and Retour change page,
  with "Première page" / "Dernière page" on line 0 at the ends (`FrameworkMessage::FirstPage`,
  `LastPage`: see [Messages](#messages)), Répétition redraws the page, and the page index is kept
  in `PageParameters`. A controller gives the number of items and of items per page, the data
  of a page from its `Paginator`, and handles its other keys, with `display($request,
  $paginator)`, `stay($paginator, $vdt)` (reads the next input on the page, after `$vdt`) and
  `turn($request, 1)` (the next page, or the end message):

  ```php
  final class DirectoryController extends PaginatedController
  {
      public function __construct(Page $page, private Directory $directory)
      {
          parent::__construct($page);
      }

      protected function itemCount(): int
      {
          return $this->directory->count();
      }

      protected function itemsPerPage(): int
      {
          return 4;
      }

      // The template also gets the paginator's variables: {{page}}, {{pageCount}}, {{itemCount}}...
      protected function pageData(Paginator $paginator): array
      {
          return ['services' => $this->directory->getServices($paginator->offset(), $paginator->limit())];
      }

      protected function onOtherKey(Request $request, Paginator $paginator): Response
      {
          return match ($request->functionKey()) {
              FunctionKey::Summary => Response::goTo('home'),
              FunctionKey::Send => $this->turn($request, 1),
              default => $this->stay($paginator),
          };
      }
  }
  ```

- **`Paginator`** is a page of `$itemCount` items, `$perPage` at a time (immutable; there is
  always one page, empty without items, and an index out of the pages is kept within them):

  | Method | Gives |
  |---|---|
  | `offset()`, `limit()` | what to retrieve for the page (e.g. `LIMIT` / `OFFSET` of a query) |
  | `slice($items)` | the page's items, out of a list of all of them |
  | `index()`, `number()` | the page, from 0 (what `PageParameters` keeps) and from 1 (what the user sees) |
  | `pageCount()`, `itemCountOnPage()` | the number of pages, and of items on this one |
  | `firstItem()`, `lastItem()` | the numbers of its first and last items, from 1 (0 without items) |
  | `isFirst()`, `isLast()`, `next()`, `previous()`, `at($index)` | where it is, and the pages around it (`next()` and `previous()`: null at the ends) |
  | `toArray()` | the variables of a template: `page`, `pageCount`, `itemCount`, `perPage`, `firstItem`, `lastItem` |

- <a id="typed-state"></a>**Typed state and parameters.** What a controller keeps is typed
  from end to end, and the static analysis checks it. A controller declares:
  - `initialState()`: a case of the string-backed enum of its states, implementing `State`
    (`ControllerState::Init` for an `AbstractController`);
  - `parametersType()`: the class of its parameters, implementing `Parameters`
    (`NoParameters` for none);

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

      public function parametersType(): string
      {
          return DirectoryParameters::class;
      }

      public function run(Request $request): Response
      {
          $page = $request->parameters()->page;          // DirectoryParameters
          if ($request->state() === SearchState::ReadQuery) {
              // ...
          }

          return Response::readInput($vdt, $command, SearchState::ReadQuery, new DirectoryParameters($page + 1));
      }
  }
  ```

  `Values` reads what the context kept (JSON, from a previous request, possibly of another
  version of the service, or forged): `int($name, $default)`, `string()`, `bool()` and
  `array()` coerce what is safe (`'12'` or `12.0` to `12`, a number to text, `1`/`0` and
  `'true'`/`'false'` to a bool), and give the default for anything else.

  Behind it, `Router::addRoute()` binds each controller with its types (`Route`), and the
  `Context` keeps a `RouteContext` per route: restored from the JSON with these types (a
  state the enum doesn't have is the initial one, unknown routes are dropped), and serialized
  as the enum's value and `toArray()`. `initialState()` and `parametersType()` are called for
  routes that don't run: they must not use the controller's properties, so that lazy
  controllers stay unbuilt.

- `Gateway\ConnectionCode` names the connection codes of `Request::key()` (`CNX`,
  `DIRECTCNX`, `FIN`, and the start of background calls, `BGCALL-WM-`): `ConnectionCode::of($key)`,
  `Request::connectionCode()`, or `GatewayRequest::$key`.

## Testing a service

`Testing\ServiceTester` scripts a session of a user against a `Service`, without the gateway,
in a PHPUnit test (PHPUnit is suggested by the framework, not required: add it to the
service's `require-dev`). It runs the service on requests built in memory, gives the context
of each answer back with the next request, and plays every answer since the connection on a
simulated `Screen`, as the Minitel keeps what isn't cleared:

```php
$tester = new ServiceTester(new Service($router, $messages));   // optionally, a Terminal

$tester->connect()->assertRoute('home')->assertScreenContains('OCTOBLUE')
    ->type('2')->press(FunctionKey::Send)->assertRoute('games')
    ->press(FunctionKey::Summary)->assertRoute('home');
```

- `connect()` starts a new session (an empty screen, no context), `type(...$lines)` gives
  the text of the input field for the next key (one string per line), `press(FunctionKey)`
  sends a key, and `disconnect()` checks a disconnection isn't answered.
- `assertScreenContains()`, `assertScreenNotContains()`, `assertLineContains($row, ...)` and
  `assertLine0()` check the screen as text, accented characters included
  (`Screen::text()`); `assertRoute()` the route the user's next key goes to, and
  `assertConnectsTo($url)` a connection to another service (`Response::connectTo()`). A
  failed assertion shows the screen.
- `screen()`, `answer()` (the last `GatewayResponse`) and `context()` give the rest, for
  other assertions.

To test the service as it is served, build its `Service` from the same wiring as its
`public/index.php`: the services of this repository keep it in `config/container.php`, which
both require (see their `tests/`).

## Messages

Text shown on the Minitel is not written in the code: it is read from messages files, by name.

- **Names** are enum cases implementing `Message\Message`, whose value is the name in the
  files: `FrameworkMessage` for the framework's (`error`, `page.first`, `page.last`,
  `endkey`, `endkey.shift`, and for the HTML view the key labels `key.*` and `web.leave`), and an enum of its own for a service's.
- **Texts** come from `config/messages.xml` in the framework (its defaults,
  `Messages::defaults()`), then the service's messages file over them, then a page's
  `<message>` over those, for that page. A text can have `{{placeholders}}`.
- **Always given:** `Service`, `PageXmlParser` and the pages take the messages as a required
  argument, never a hidden default: pass `Messages::defaults()` for the framework's only.

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
$messages = (new MessagesXmlParser())
    ->parseFile("$config/messages.xml", Messages::defaults())
    ->check(FrameworkMessage::class, AccMessage::class);
$parser = new PageXmlParser($messages);              // pages redefine them with <message>
$service = new Service($router, $messages, $logger);  // for its error message

// In a controller: the page's text, its placeholders replaced
Vdt::line0($this->page->message(AccMessage::ConnectedFrom, ['remoteAddr' => $addr]));
```

A message without text, or a placeholder without its variable, is a `LogicException`; a
messages file that can't be read is a `MessagesException` with the file and line.
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
| `{{name}}`, `{{service.code}}` | placeholder, in any text or attribute (not in `<input>`); dotted paths go through arrays and object properties |
| `<foreach items="services" as="service" row="5" step="2">…</foreach>` | repeats its content for each item, `step` rows apart. Inside, rows are relative to the item (0 = its first row), and `loop.index` (from 1), `loop.count`, `loop.first` and `loop.last` are available |
| `<if test="loop.last">…</if>` | content shown only if the variable is set and not empty |
| `<if test="!loop.last">…</if>` | … or only if it is not |

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
one (`Page::display()`), responses and `Flow::answer()` take one, and `GatewayResponse` holds
one. Only the gateway client (`MiniPaviClient::send()`) turns it into bytes. Text and
Videotex can't be mixed up:

```php
Vdt::text('Déjà vu');                       // text: its accents encoded in G2
new Vdt("\x0C");                            // raw Videotex (or Vdt::fromString())
Vdt::line0('Choix inconnu');               // on the status line (MiniPaviCli's writeLine0())
$page->display($terminal)->append(Vdt::line0('Bienvenue'));  // one after the other
Vdt::join($a, $b, $c);
```

A `Vdt` keeps its bytes, and reads them only to play, show or move them (below).

Codes to send as they are have names: `Vdt\Videotex` for Videotex (`CLEAR_LINE`,
`CURSOR_OFF`, `INVERSE_ON`, sizes, `RESET_CHARSETS`...), and the `Minitel\Keyboard`,
`Minitel\LocalEcho` and `Minitel\Scroll` enums for the sequences configuring the Minitel
(`Keyboard::Lowercase->sequence()`...). A page sends them itself, from the attributes of
`<page>` (see [Page XML format](#page-xml-format)); `Page\Setup` sends them for other content.

They replace MiniPaviCli's global `VDT_*` and `PRO_*` constants, which are only defined once
`MiniPaviCli` is loaded. `Vdt` gives MiniPaviCli's Videotex helpers as its constructors:
`Vdt::text()` (its `toG2()`), `Vdt::position($col, $row)` (`setPos()`), `Vdt::line0()`
(`writeLine0()`), `Vdt::clearScreen()` and `Vdt::repeat($char, $count)` (`repeatChar()`).

`Vdt` also reads Videotex content, e.g. of a `.vdt` file, and moves it on the screen;
`<image>` uses it.

```php
use MiniPaviService\Vdt\Vdt;

$picture = Vdt::fromString(file_get_contents('octopus.vdt'));
$moved = $picture->offset(8, -2);     // 8 columns right, 2 rows up (negative: left, up)
echo $moved->toString();               // the Videotex content
echo $moved->screen()->preview();      // the screen, as text
```

- **`offset($x, $y)`** (`Vdt\Offset`): Videotex positions are mostly absolute, but text also flows from cell
  to cell and wraps at column 40, so the content is played on a simulated screen (`Vdt\Player`) rather than
  having its positions patched. Characters that end up off the screen are dropped instead of
  wrapping onto the next row, and the cursor is positioned again where needed, with the
  current attributes (character set, colors, size...), which a positioning resets. Row 0,
  the status line, is not moved.
- **`withoutClear()`**: the same content without its clear screens (FF), to draw it over
  other content.
- **`HtmlRenderer::render($screen, $title)`**: the screen as an HTML page (see `HtmlClient`
  above).
- **`screen()`**: the `Screen` drawn by the content: a `Cell` (character and attributes) or
  `null` per row and column. `preview()` prints it as text, with row numbers (G2 characters
  as `?`), and `line($row)` and `text()` give the text of a row and of the screen, G2
  characters decoded (`é`, `£`, `½`, `Œ`...; `Cell::text()`).
- **`play($glyph, $other)`**: plays the content on the simulated screen, calling `$glyph`
  for each character, with its row, column, width and attributes, and `$other` for the
  other `Token`s.

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
  with 1 on an error, or if it found no page. The services of this repository run it with
  `composer lint`, in CI.
- **`preview [--messages <messages.xml>] <page.xml> [<data.json>]`** prints the screen a page
  draws, as text (`Screen::preview()`): a template with the variables of a JSON object (e.g.
  `{"games": [{"name": "Alien Wipeout"}]}`).
- **`vdt-offset [--preview] <input.vdt> <x> <y> [<output.vdt>]`** moves the content of a
  Videotex page by `x` columns and `y` rows (negative: left, up), with `Vdt::offset()` (see
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
```

Tests are in `tests/`, with their files in `tests/fixtures/`. Pages are checked through the
screen they draw (`Screen::preview()`). `Vdt::offset()` is checked in two ways: against the
property that the moved screen is the original one moved, and byte for byte against golden
files (`tests/fixtures/vdt/*.offset_*.vdt`). Run `UPDATE_GOLDEN=1 composer test` to rewrite
the golden files after an intended change.

GitHub Actions runs the three checks on PHP 8.2, 8.3, 8.4 and 8.5 for every change to the
framework.
