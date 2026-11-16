<?php

declare(strict_types=1);

// MiniPaviCli defines its VDT_*, PRO_* and MSK_* constants in its class file, which the
// autoloader would only load on the first use of the class. The framework uses them earlier
// (e.g. MSK_ENVOI when parsing a page), so the class is loaded as soon as Composer's
// autoloader is required.
class_exists(\MiniPavi\MiniPaviCli::class);
