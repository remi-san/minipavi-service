<?php

declare(strict_types=1);

namespace MiniPaviService\Message;

/**
 * Name of a message shown on the Minitel: a case of a string-backed enum implementing this
 * interface, whose value is the name of the message in a messages file (<message name>).
 * FrameworkMessage has the framework's; a service names its own in its own enum.
 */
interface Message extends \BackedEnum {}
