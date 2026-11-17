<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

/**
 * Messages the framework shows, defined in its config/messages.xml
 */
enum FrameworkMessage: string implements Message
{
    // On line 0 when a request fails: any function key then leads to the default route
    case Error = 'error';
    // On line 0 for Retour on the first page of a PaginatedController
    case FirstPage = 'page.first';
    // On line 0 for Suite on its last page
    case LastPage = 'page.last';
    // Label of the key of <endkey>, telling how to come back to the screen
    case EndKey = 'endkey';
    // Before it, for ESP32/Minimit adapters, which need Shift with the key
    case EndKeyShift = 'endkey.shift';
}
