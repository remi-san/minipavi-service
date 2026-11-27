<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

use MiniPaviService\MiniPaviServiceException;

/**
 * Template that cannot be displayed with the data it was given: unknown variable,
 * value that is not text, or element ending up off the screen
 */
final class TemplateException extends \RuntimeException implements MiniPaviServiceException {}
