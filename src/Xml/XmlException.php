<?php

declare(strict_types=1);

namespace MiniPaviService\Xml;

use MiniPaviService\MiniPaviServiceException;

/**
 * An XML file that can't be read as expected, the message giving the file and line: what each
 * parser rethrows as its own exception (PageXmlException, MessagesException)
 *
 * @internal
 */
final class XmlException extends \RuntimeException implements MiniPaviServiceException {}
