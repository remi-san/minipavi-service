<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

/**
 * States AbstractController runs: Init (the first run, or when the user comes back), Display
 * (shows the screen), InitInput (asks for input again) and ReadInput (handles what the user
 * typed or pressed)
 */
enum ControllerState: string implements State
{
    case Init = 'init';
    case Display = 'display';
    case InitInput = 'init-input';
    case ReadInput = 'read-input';
}
