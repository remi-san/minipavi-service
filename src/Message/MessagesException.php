<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\MiniPaviServiceException;

/**
 * A messages file that can't be read (with its file and line), messages that miss some, or a
 * text with a {{...}} that isn't a placeholder
 */
final class MessagesException extends \RuntimeException implements MiniPaviServiceException {}
