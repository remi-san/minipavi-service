<?php

namespace MiniPaviService;

use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;

interface Controller
{
    public const STATE_INIT = 'init';
    public const STATE_DISPLAY = 'display';
    public const STATE_INIT_INPUT = 'init-input';
    public const STATE_READ_INPUT = 'read-input';

    public function run(Request $request): Response;
}
