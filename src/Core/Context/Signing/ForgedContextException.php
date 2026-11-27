<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Context\Signing;

use MiniPaviService\MiniPaviServiceException;

/**
 * A context the service didn't sign (ContextSigner::verify()): it isn't restored, and is
 * responded to as a context that can't be read (Core\Context\UnreadableContextException) is
 */
final class ForgedContextException extends \RuntimeException implements MiniPaviServiceException {}
