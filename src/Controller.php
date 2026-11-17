<?php

declare(strict_types=1);

namespace MiniPaviService;

use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;

/**
 * Runs on a request of the user, and returns what comes next: see AbstractController for one
 * running a method per state (ControllerState)
 */
interface Controller
{
    public function run(Request $request): Response;
}
