<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * State of a client that needs none (e.g. MiniPaviClient), and of requests built in memory
 */
final readonly class NoClientState implements ClientState {}
