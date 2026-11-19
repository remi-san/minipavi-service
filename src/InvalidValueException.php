<?php

declare(strict_types=1);

namespace MiniPaviService;

/**
 * A value the framework can't take, e.g. a position off the screen (Minitel\Position), or a
 * placeholder naming an unknown variable (Text\Placeholders). Where the value comes from a
 * page or a message, it is reported with the page's file and line (PageXmlException,
 * TemplateException) or the message's name; any other exception, e.g. of a service's own
 * ElementBuilder or of a variable's code, goes through as it is.
 */
final class InvalidValueException extends \InvalidArgumentException {}
