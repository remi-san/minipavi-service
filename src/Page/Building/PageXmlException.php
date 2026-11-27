<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Building;

use MiniPaviService\MiniPaviServiceException;

/**
 * Page XML file missing, invalid, or describing something that cannot be displayed
 */
final class PageXmlException extends \RuntimeException implements MiniPaviServiceException {}
