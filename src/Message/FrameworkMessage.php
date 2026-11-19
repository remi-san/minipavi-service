<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

use MiniPaviService\Minitel\FunctionKey;

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
    // Labels of the function keys, on the buttons of the HTML view (?web=true)
    case KeySummary = 'key.summary';
    case KeyCancel = 'key.cancel';
    case KeyPrevious = 'key.previous';
    case KeyRefresh = 'key.refresh';
    case KeyGuide = 'key.guide';
    case KeyCorrection = 'key.correction';
    case KeyNext = 'key.next';
    case KeySend = 'key.send';
    // On line 0 of the HTML view, when a key would leave the service (RunCommand::connectTo())
    case WebLeave = 'web.leave';

    /**
     * Label of a function key
     */
    public static function keyLabel(FunctionKey $key): self
    {
        return match ($key) {
            FunctionKey::Summary => self::KeySummary,
            FunctionKey::Cancel => self::KeyCancel,
            FunctionKey::Previous => self::KeyPrevious,
            FunctionKey::Refresh => self::KeyRefresh,
            FunctionKey::Guide => self::KeyGuide,
            FunctionKey::Correction => self::KeyCorrection,
            FunctionKey::Next => self::KeyNext,
            FunctionKey::Send => self::KeySend,
        };
    }
}
