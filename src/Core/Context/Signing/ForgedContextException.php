<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Context\Signing;

/**
 * A context the service didn't sign (ContextSigner::verify()): it isn't restored, as a context
 * that can't be read
 */
final class ForgedContextException extends \RuntimeException {}
