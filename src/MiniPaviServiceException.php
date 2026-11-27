<?php

declare(strict_types=1);

namespace MiniPaviService;

/**
 * An error of the framework, which each of its exceptions implements, keeping its own SPL
 * parent (InvalidValueException is an \InvalidArgumentException, PageXmlException a
 * \RuntimeException...): a service catches them all as one, or each by its type. A mistake in
 * a service's code that the framework finds (e.g. a default route that isn't a route, a
 * controller keeping another type of state) is a \LogicException, not one of these: it is
 * fixed in the code, not caught. An exception of a service's own code (an ElementBuilder, a
 * variable's __toString()...) goes through as it is.
 */
interface MiniPaviServiceException extends \Throwable {}
