<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

/**
 * A messages file that can't be read (with its file and line), or messages that miss some
 */
final class MessagesException extends \RuntimeException {}
