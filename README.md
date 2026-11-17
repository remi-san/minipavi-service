# MiniPavi service framework

PHP framework for Minitel services behind a [MiniPavi](https://www.minipavi.fr/) gateway. It builds on the
official [`ludosevilla/minipavi-cli`](https://packagist.org/packages/ludosevilla/minipavi-cli).

- **Pages** described in XML: static, or templates with placeholders, loops and conditions
- **Controllers**, one per screen, each a small state machine returning **responses**
- A **router** and a **context** keeping each controller's state between requests
- A **service without I/O**: it answers a `GatewayRequest` with a `GatewayAnswer`, which
  `MiniPaviClient` reads from and sends to the gateway, through the static `MiniPaviCli`
- **`Vdt`**, to read Videotex content (e.g. pictures made with a Videotex editor) and move it on the screen

Requires PHP 8.2.

The code (namespace `MiniPaviService`, in `src/`) is organized by layer:

| Namespace | Holds |
|---|---|
| `MiniPaviService` | the `Controller` and `Page` interfaces |
| `Core\` | the runtime: `Service`, `Context`, `Router`, and the values `Terminal` and `FunctionKey` |
| `Controller\` | `AbstractController`, `Request`, `Response` and the response types |
| `Page\` | static and template pages, their display elements, and the page XML parser |
| `Vdt\` | Videotex codes, reading and moving Videotex content |
| `Gateway\` | the MiniPavi gateway: its requests and answers (`GatewayRequest`, `GatewayAnswer`), and `MiniPaviClient`, which reads and sends them |
| `Http\` | the entry point for the web server: `ServiceRunner`, `RequestUrl`, `EmulatorConfig`, `ServerVariable` |
| `Log\` | `ErrorLogLogger` |
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
use MiniPaviService\Core\{Router, Service};
use MiniPaviService\Gateway\MiniPaviClient;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Log\ErrorLogLogger;
use MiniPaviService\Page\Parser\PageXmlParser;

require __DIR__.'/../vendor/autoload.php';

$router = new Router();
$router->addRoute(HomeController::NAME, new HomeController((new PageXmlParser())->parseFile(__DIR__.'/../config/pages/home.xml')));
$router->setDefaultRoute(HomeController::NAME);

$logger = new ErrorLogLogger();

(new ServiceRunner(new Service($router, $logger), new MiniPaviClient(), logger: $logger))->run();
```

- **Lazy controllers:** a service with many screens shouldn't build every controller, and parse
  every page, on each request. With a dependency injection container such as PHP-DI, define
  the controllers as lazy: the router gets proxies, and a controller is only built when its
  route runs (PHP 8.4's native lazy objects, which keep controllers `final`). See
  `services/*/public/index.php`:

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

- **`ServiceRunner`** runs the service for the gateway, through the `MiniPaviClient` it is given:
  the client reads the request, the service answers it, and the client sends the answer. A request that can't be read
  is answered as an error (see `Service`). It redirects browsers to an emulator that opens the
  service: with `EMULATOR_URL` set (the local stack), that emulator and the
  gateway `GATEWAY_URL`, which reaches the service at `SERVICES_INTERNAL_HOST`; otherwise (a
  public server), the minipavi.fr emulator and gateway, with the URL the browser asked for.
- **`RequestUrl`** builds the service's URLs for `ServiceRunner` (where browsers come back)
  and `MiniPaviClient` (the next URL given to the gateway): https when the web server says
  the request came over TLS (`HTTPS`, port 443). Both read from the environment:
  - `SERVICE_URL` (e.g. `https://minitel.example.org`): scheme and host to use instead of the
    request's;
  - `TRUSTED_PROXIES` (e.g. `10.0.0.1, 172.16.0.0/12`): reverse proxies whose
    `X-Forwarded-Proto` is believed, when they terminate TLS. From anyone else, the header
    is ignored.
- **`Service`** answers a request of the gateway, without I/O:
  `$service->handle(GatewayRequest): ?GatewayAnswer` (null for a disconnection, which has
  nothing to answer). It restores the `Context`, and runs the controller of the current route
  until a response answers the gateway. `Service` owns the context: it keeps the state and
  parameters a response gives for its controller, then the response says what comes next,
  through a `Flow` (see below). A context without route, or
  whose route the service doesn't have (e.g. removed in a new version), starts again at the
  default route.
  If serving a request fails (an exception or a PHP error, in a controller, a template, the
  context...), the error is logged, and the user gets a message on line 0 (`$errorMessage` of
  the constructor, by default `Service::ERROR_MESSAGE`): their next function key starts again
  at the default route.
- **`GatewayRequest`** is what the gateway sends: `key` (the function key, or a connection
  code), `content` (the text typed), `context` (given back from the previous answer) and
  `terminal`. **`GatewayAnswer`** is what is sent back: `vdt`, `context`, `next` (the URL the
  gateway calls next, null for this service's), `command` (e.g. an input command),
  `directCall` (a `CallMode`, to call `next` at once) and `echo`. Both are plain values, so a
  service can be tested, or run elsewhere than PHP's web server, without `MiniPaviCli`.
- **Pages** are displayed for the user's terminal, which `<endkey>` depends on:
  `$page->display($request->terminal(), $data)`; display elements get it too
  (`Element::getContent(Terminal $terminal)`).
- **Logging:** `Service` and `ServiceRunner` take an optional PSR-3 logger (e.g. Monolog, or
  the framework's `ErrorLogLogger`, writing to PHP's error log) and
  log connections, disconnections and browsers sent to the emulator at info level, and errors
  at error level (with the exception):
  `new Service($router, $logger)`, `new ServiceRunner($service, $client, logger: $logger)`.
  Without one, nothing is logged.
- **A `Controller`** runs on a `Request` and returns a `Response`. The request gives:
  - `state()` and `parameters()`: what the controller's previous response asked to keep,
    from one request of the gateway to the next, typed as the controller declares them (see
    [Typed state and parameters](#typed-state)): a case of its `State` enum
    (`ControllerState`: `Init`, `Display`, `InitInput`, `ReadInput`, or its own), and an
    object of its `Parameters` class;
  - `functionKey()`: the function key the user pressed, a `FunctionKey` (`Send`, `Next`,
    `Previous`, `Refresh`, `Summary`, `Guide`, `Cancel`, `Correction`), or null for a
    connection code; `key()` gives the name the gateway sent (`ENVOI`…, or `CNX`, `FIN`…);
  - `content()`: the text typed, one string per line of the input field, and
    `contentAsString()`;
  - `terminal()`: where the user connects from (`remoteAddr()`, `typeSocket()`,
    `versionMinitel()`).

  `Response`'s named constructors build the responses:

  | Response | Effect | State kept by default |
  |---|---|---|
  | `Response::display($vdt)` | sends Videotex content to the gateway; the next key comes back to this controller | `ControllerState::ReadInput` |
  | `Response::readInput($vdt, $command)` | sends content and the command reading the page's input field (`Page::inputCommand()`) to the gateway | `ControllerState::ReadInput` |
  | `Response::goTo($route, $parameters)` | switches to another controller, which runs at once: at `ControllerState::Init`, with `$parameters` (a `Parameters` object, or none), without the user's input; this controller is reset | `ControllerState::Init` |
  | `Response::connectTo($url, $vdt)` | sends the user to another service, as a new connection (`CallMode::Connect`) | `ControllerState::Init` |

  Every response but `goTo()` answers the gateway and ends the request. The state and
  parameters are what this controller keeps for its next run (for `connectTo()`, when the
  user comes back to it): a `State`, and a `Parameters` object (none by default). Give
  others by name, e.g. `Response::readInput($vdt, $command, parameters: new
  DirectoryParameters(2))`. The classes behind them
  (`DisplayPage`, `RunCommand`, `ChangePage`, `RunExternalService`) can also be built directly.
  They are readonly, and don't change the context. Once `Service` has kept a response's
  state and parameters, it calls `continueWith(Flow $flow): GatewayAnswer`, where the
  response says what comes next: `$flow->answer($vdt, $command, $next, $directCall)` ends
  the request with that answer to the gateway, and `$flow->goTo($route, $parameters)` runs
  another controller and gives its answer. A response of your own extends `Response` and
  implements `continueWith()`, without a change to `Service`:

  ```php
  final readonly class ClearAndShow extends Response
  {
      public function __construct(private string $vdt)
      {
          parent::__construct(ControllerState::ReadInput, null);
      }

      public function continueWith(Flow $flow): GatewayAnswer
      {
          return $flow->answer(VdtHelper::clearScreen() . $this->vdt);
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

          return Response::readInput(VdtHelper::writeLine0('Unknown code'), $this->page->inputCommand());
      }
  }
  ```

  A state it doesn't know (e.g. kept by an older version of the service) starts it again.
  A controller with states of its own implements `Controller` with its own `State` enum.

- <a id="typed-state"></a>**Typed state and parameters.** What a controller keeps is typed
  from end to end, and PHPStan checks it. A controller declares:
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

- The `FCTN_*` constants of `GatewayRequest` name the connection codes of `Request::key()`
  (`CNX`, `DIRECTCNX`, `FIN`).

## Testing a service

`Testing\ServiceTester` scripts a session of a user against a `Service`, without the gateway,
in a PHPUnit test (PHPUnit is suggested by the framework, not required: add it to the
service's `require-dev`). It runs the service on requests built in memory, gives the context
of each answer back with the next request, and plays every answer since the connection on a
simulated `Screen`, as the Minitel keeps what isn't cleared:

```php
$tester = new ServiceTester(new Service($router));   // optionally, a Terminal

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
- `screen()`, `answer()` (the last `GatewayAnswer`) and `context()` give the rest, for
  other assertions.

To test the service as it is served, build its `Service` from the same wiring as its
`public/index.php`: the services of this repository keep it in `config/container.php`, which
both require (see their `tests/`).

## Page XML format

A page file has a `<page>` root, read by `PageXmlParser`. Errors throw a `PageXmlException`
giving the file and line. The screen is 40 columns × 24 rows (row 0 is the status line), and
elements are drawn in order:

| Element | Draws |
|---|---|
| `<background>file.vdt</background>` | Videotex page from the page's folder or one of its subfolders (e.g. `images/logo.vdt`), sent as is (e.g. made with a Videotex editor). Put it first to draw over it. |
| `<image row col>file.vdt</image>` | Videotex picture from the page's folder or one of its subfolders, drawn with its top-left cell (row 1, column 1 of the file) at `row`, `col` (default 1, 1): what goes past the screen is cut, and it doesn't clear the screen. In a template, the file name can be a placeholder (`{{game.image}}`), and in a `<foreach>`, `row` is relative to the item, to show a picture per item |
| `<text row col align="center" color bg size fill="yes">…</text>` | text; `size`: normal, double-height, double-width, double; `fill="yes"` paints the rest of the row with `bg` |
| `<line row col length char color/>` | horizontal line; `char`: top (`_`), middle (`` ` ``), bottom (`~`) or any character |
| `<key row col color bg>Envoi</key>` | function key badge, in reverse video (`bg`: around it) |
| `<endkey row color/>` | "Connexion/fin" hint, with "[Maj] +" for ESP32/Minimit adapters |
| `<input row col length lines validwith/>` | input field read by `Page::inputCommand()` (default: row 13, 2 lines, to the end of the row) |

- `validwith`: keys validating the input, separated by spaces (default `envoi`): envoi, sommaire,
  annulation, retour, repetition, guide, correction, suite (`FunctionKey::xmlName()`). A one-line field is a text input
  where every key validates; with several lines, Suite and Retour move between them.
- Colors: black, red, green, yellow, blue, magenta, cyan, white. `col` defaults to 1.

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

Codes to send as they are have names: `Vdt\Videotex` for Videotex (`CLEAR_LINE`,
`CURSOR_OFF`, `INVERSE_ON`, sizes, `RESET_CHARSETS`...), and `Vdt\Protocol` for the sequences
configuring the Minitel, typically sent before a page:

```php
$vdt = Protocol::KEYBOARD_LOWERCASE . Protocol::LOCAL_ECHO_OFF . Protocol::SCROLL_OFF . $page->display($request->terminal());
```

They replace MiniPaviCli's global `VDT_*` and `PRO_*` constants, which are only defined once
`MiniPaviCli` is loaded. `VdtHelper` gives MiniPaviCli's Videotex helpers (`setPos()`,
`writeLine0()`, `toG2()`...).

`MiniPaviService\Vdt\Vdt` reads Videotex content, from a string or a `.vdt` file, and moves
it on the screen; `<image>` uses it.

```php
use MiniPaviService\Vdt\Vdt;

$picture = Vdt::fromFile('octopus.vdt');
$moved = $picture->offset(8, -2);     // 8 columns right, 2 rows up (negative: left, up)
echo $moved->toString();               // the Videotex content
echo $moved->screen()->preview();      // the screen, as text
```

- **`offset($x, $y)`**: Videotex positions are mostly absolute, but text also flows from cell
  to cell and wraps at column 40, so the content is played on a simulated screen rather than
  having its positions patched. Characters that end up off the screen are dropped instead of
  wrapping onto the next row, and the cursor is positioned again where needed, with the
  current attributes (character set, colors, size...), which a positioning resets. Row 0,
  the status line, is not moved.
- **`withoutClear()`**: the same content without its clear screens (FF), to draw it over
  other content.
- **`screen()`**: the `Screen` drawn by the content: a `Cell` (character and attributes) or
  `null` per row and column. `preview()` prints it as text, with row numbers (G2 characters
  as `?`), and `line($row)` and `text()` give the text of a row and of the screen, G2
  characters decoded (`é`, `£`, `½`, `Œ`...; `Cell::text()`).
- **`play($glyph, $other)`**: plays the content on the simulated screen, calling `$glyph`
  for each character, with its row, column, width and attributes, and `$other` for the
  other `Token`s.

`tool/vdt-offset` (at the root of the repository) does the same from the command line.

## Developing the framework

The code follows [PER Coding Style](https://www.php-fig.org/per/coding-style/), with
`declare(strict_types=1)` in every file, short arrays and sorted imports, checked by
PHP-CS-Fixer (configuration in `.php-cs-fixer.dist.php`):

```sh
composer install    # once, in this folder: development tools in vendor/
composer cs         # checks the style, showing what would change
composer cs-fix     # fixes it
composer analyse    # static analysis (PHPStan, phpstan.dist.neon)
composer test       # tests (PHPUnit, tests/)
```

Tests are in `tests/`, with their files in `tests/fixtures/`. Pages are checked through the
screen they draw (`Screen::preview()`). `Vdt::offset()` is checked in two ways: against the
property that the moved screen is the original one moved, and byte for byte against golden
files (`tests/fixtures/vdt/*.offset_*.vdt`). Run `UPDATE_GOLDEN=1 composer test` to rewrite
the golden files after an intended change.

GitHub Actions runs the three checks on PHP 8.2, 8.3, 8.4 and 8.5 for every change to the
framework.
