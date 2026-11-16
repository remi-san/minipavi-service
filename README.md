# MiniPavi service framework

PHP framework for Minitel services behind a [MiniPavi](https://www.minipavi.fr/) gateway. It builds on the
official [`ludosevilla/minipavi-cli`](https://packagist.org/packages/ludosevilla/minipavi-cli).

- **Pages** described in XML: static, or templates with placeholders, loops and conditions
- **Controllers**, one per screen, each a small state machine returning **responses**
- A **router** and a **context** keeping each controller's state between requests
- **`MiniPaviClient`**, an instance wrapper of the static `MiniPaviCli`
- **`Vdt`**, to read Videotex content (e.g. pictures made with a Videotex editor) and move it on the screen

Requires PHP 8.2.

## Installing

From a service in this repository, as a `path` repository (Composer symlinks it into `vendor/`,
so edits to the framework apply without reinstalling):

```json
{
    "repositories": [{ "type": "path", "url": "../../framework" }],
    "require": { "minipavi-stack/framework": "*@dev" }
}
```

In Docker, the framework and the service keep the repository's layout (`/app/framework` and
`/app/services/<service>`), so that the relative path and the symlink resolve there too.

## Writing a service

`public/index.php` builds the pages and controllers, and hands them to the service:

```php
use MiniPaviService\{MiniPaviClient, Router, Service, ServiceRunner};
use MiniPaviService\Page\Parser\PageXmlParser;

require __DIR__.'/../vendor/autoload.php';

$client = new MiniPaviClient();
$pages = new PageXmlParser($client);

$router = new Router();
$router->addRoute(HomeController::NAME, new HomeController($client, $pages->parseFile(__DIR__.'/../config/pages/home.xml')));
$router->setDefaultRoute(HomeController::NAME);

(new ServiceRunner(new Service($client, $router)))->run();
```

- **`ServiceRunner`** runs the service for the gateway, and redirects browsers to an emulator
  that opens the service: with `EMULATOR_URL` set (the local stack), that emulator and the
  gateway `GATEWAY_URL`, which reaches the service at `SERVICES_INTERNAL_HOST`; otherwise (a
  public server), the minipavi.fr emulator and gateway, with the URL the browser asked for.
- **`Service`** reads the gateway's request, restores the `Context`, and runs the controller of
  the current route until a response is sent to the gateway.
- **A `Controller`** has a state (`STATE_INIT`, `STATE_DISPLAY`, `STATE_INIT_INPUT`,
  `STATE_READ_INPUT`) and data, both kept in the context from one request to the next
  (`state()`/`setState()`, `data()`/`setData()`). `run()` returns a `Response`:

  | Response | Effect |
  |---|---|
  | `ChangePage($route)` | switches to another controller, and runs it |
  | `DisplayPage($vdt)` | sends Videotex content to the gateway; the next key comes back to this service |
  | `RunCommand($vdt, $cmd)` | sends content and an input command (`Page::readInput()`) to the gateway |
  | `RunExternalService($vdt, $url, $mode)` | sends the user to another service (`CALL_MODE_CONNECT`: right away) |

  Every response but `ChangePage` answers the gateway and ends the request.

- The `KEY_*` and `FCTN_*` constants of `MiniPaviClient` name the function keys and connection
  codes in `MiniPaviClient::fctn()`.

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
| `<input row col length lines validwith/>` | input field read by `Page::readInput()` (default: row 13, 2 lines, to the end of the row) |

- `validwith`: keys validating the input, separated by spaces (default `envoi`): envoi, sommaire,
  annulation, retour, repetition, guide, correction, suite. A one-line field is a text input
  where every key validates; with several lines, Suite and Retour move between them.
- Colors: black, red, green, yellow, blue, magenta, cyan, white. `col` defaults to 1.

`<page type="static">` is drawn as is. `<page type="template">` can also have placeholders and
instructions, filled in with the data given to `Page::display($data)`:

| Syntax | Meaning |
|---|---|
| `{{name}}`, `{{service.code}}` | placeholder, in any text or attribute (not in `<input>`); dotted paths go through arrays and object properties |
| `<foreach items="services" as="service" row="5" step="2">…</foreach>` | repeats its content for each item, `step` rows apart. Inside, rows are relative to the item (0 = its first row), and `loop.index` (from 1), `loop.count`, `loop.first` and `loop.last` are available |
| `<if test="loop.last">…</if>` | content shown only if the variable is set and not empty |
| `<if test="!loop.last">…</if>` | … or only if it is not |

## Videotex content

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
