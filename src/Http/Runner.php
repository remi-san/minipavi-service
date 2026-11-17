<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

/**
 * Handles the HTTP request of the web server: what public/index.php runs
 */
interface Runner
{
    public function run(): void;
}
