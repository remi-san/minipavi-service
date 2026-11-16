# MiniPavi service framework

PHP framework for Minitel services behind a [MiniPavi](https://www.minipavi.fr/) gateway. It builds on the
official [`ludosevilla/minipavi-cli`](https://packagist.org/packages/ludosevilla/minipavi-cli).

- **Pages** described in XML: static, or templates with placeholders, loops and conditions
- **Controllers**, one per screen, each a small state machine returning **responses**
- A **router** and a **context** keeping each controller's state between requests
- **`MiniPaviClient`**, an instance wrapper of the static `MiniPaviCli`
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
| `Gateway\` | `MiniPaviClient`, the client of the MiniPavi gateway |
| `Http\` | the entry point for the web server: `ServiceRunner`, `RequestUrl`, `EmulatorConfig`, `ServerVariable` |
| `Log\` | `ErrorLogLogger` |

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

(new ServiceRunner(new Service(new MiniPaviClient(), $router, $logger), logger: $logger))->run();
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

- **`ServiceRunner`** runs the service for the gateway, and redirects browsers to an emulator
  that opens the service: with `EMULATOR_URL` set (the local stack), that emulator and the
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
- **`Service`** reads the gateway's request, restores the `Context`, and runs the controller of
  the current route until a response is sent to the gateway. A context without route, or
  whose route the service doesn't have (e.g. removed in a new version), starts again at the
  default route.
  If serving a request fails (an exception or a PHP error, in a controller, a template, the
  context...), the error is logged, and the user gets a message on line 0 (`$errorMessage` of
  the constructor, by default `Service::ERROR_MESSAGE`): their next function key starts again
  at the default route.
- **Pages** are displayed for the user's terminal, which `<endkey>` depends on:
  `$page->display($request->terminal(), $data)`; display elements get it too
  (`Element::getContent(Terminal $terminal)`).
- **Logging:** `Service` and `ServiceRunner` take an optional PSR-3 logger (e.g. Monolog, or
  the framework's `ErrorLogLogger`, writing to PHP's error log) and
  log connections, disconnections and browsers sent to the emulator at info level, and errors
  at error level (with the exception):
  `new Service($client, $router, $logger)`, `new ServiceRunner($service, logger: $logger)`.
  Without one, nothing is logged.
- **A `Controller`** runs on a `Request` and returns a `Response`. The request gives:
  - `state()` (`STATE_INIT`, `STATE_DISPLAY`, `STATE_INIT_INPUT`, `STATE_READ_INPUT`) and
    `parameters()` / `parameter($name)`: what the controller's previous response asked to
    keep, from one request of the gateway to the next (the context keeps them per route, and
    goes to the gateway as JSON: parameters are text, numbers, booleans, null or arrays);
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
  | `Response::display($vdt)` | sends Videotex content to the gateway; the next key comes back to this controller | `STATE_READ_INPUT` |
  | `Response::readInput($vdt, $command)` | sends content and the command reading the page's input field (`Page::inputCommand()`) to the gateway | `STATE_READ_INPUT` |
  | `Response::goTo($route, $parameters)` | switches to another controller, which runs at once: at `STATE_INIT`, with `$parameters`, without the user's input; this controller is reset | `STATE_INIT` |
  | `Response::connectTo($url, $vdt)` | sends the user to another service, as a new connection (`CallMode::Connect`) | `STATE_INIT` |

  Every response but `goTo()` answers the gateway and ends the request. The state and
  parameters are what this controller keeps for its next run (for `connectTo()`, when the
  user comes back to it); give others by name, e.g.
  `Response::readInput($vdt, $command, parameters: ['page' => 2])`. The classes behind them
  (`DisplayPage`, `RunCommand`, `ChangePage`, `RunExternalService`) can also be built directly.

- **`AbstractController`** runs the method of the request's state, so a controller only
  writes what each state does:

  ```php
  final class HomeController extends AbstractController
  {
      public const NAME = 'home';

      public function __construct(private Page $page) {}

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
  To add states, override `run()` and call `parent::run()` for the others.

- The `FCTN_*` constants of `MiniPaviClient` name the connection codes of `Request::key()`
  (`CNX`, `DIRECTCNX`, `FIN`).

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
  `null` per row and column, and `preview()` to print it as text.
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
