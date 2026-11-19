<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Parameters;

use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;

/**
 * Parameters of a controller, as an immutable object (a readonly class with a property per
 * parameter): the controller declares their type (Controller::parametersType(), a
 * ParametersType of this class), gets them typed (Request::parameters()) and keeps them by
 * giving them to a Response. NoParameters (ParametersType::none()) for a controller without
 * any. The context keeps the object Request::parameters() gives: changing it would change
 * what the route keeps, without a Response keeping it.
 */
interface Parameters
{
    /**
     * The parameters from the values the context keeps: read them with Values::int(),
     * string(), bool() and array(), so a missing or invalid value (e.g. from another version
     * of the service) gets its default. Empty values give the defaults.
     */
    public static function fromValues(Values $values): static;

    /**
     * The values the context keeps: name => JSON value (text, number, bool, null or array)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
