<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

/**
 * Parameters of a controller as an object, e.g. a readonly class with a property per
 * parameter: read with Request::parametersAs(), kept by giving it to a Response
 * ($parameters), so PHPStan checks what the controller keeps.
 */
interface Parameters
{
    /**
     * The parameters of the request (kept by the previous response), typed: read them with
     * Request::int(), string(), bool() and array(), so a missing or invalid value (e.g. from
     * another version of the service) gets its default
     */
    public static function fromRequest(Request $request): static;

    /**
     * The parameters kept in the context: name => JSON value (text, number, bool, null or
     * array)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
