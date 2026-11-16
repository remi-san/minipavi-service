# MiniPavi service framework

PHP framework for Minitel services behind a [MiniPavi](https://www.minipavi.fr/) gateway. It builds on the
official [`ludosevilla/minipavi-cli`](https://packagist.org/packages/ludosevilla/minipavi-cli).

- **Pages** described in XML: static, or templates with placeholders, loops and conditions
- **Controllers**, one per screen, each a small state machine returning **responses**
- A **router** and a **context** keeping each controller's state between requests
- **`MiniPaviClient`**, an instance wrapper of the static `MiniPaviCli`

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

- **`ServiceRunner`** redirects browsers (anything but the gateway) to the local emulator,
  using `EMULATOR_URL`, `GATEWAY_URL` and `SERVICES_INTERNAL_HOST`, then runs the service.
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

- The `KEY_*` and `FCTN_*` constants of `Controller` name the function keys and connection
  codes in `MiniPaviClient::fctn()`.

## Page XML format

A page file has a `<page>` root, read by `PageXmlParser`. Errors throw a `PageXmlException`
giving the file and line. The screen is 40 columns × 24 rows (row 0 is the status line), and
elements are drawn in order:

| Element | Draws |
|---|---|
| `<background>file.vdt</background>` | Videotex page from the page's folder, sent as is (e.g. made with a Videotex editor). Put it first to draw over it. |
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
