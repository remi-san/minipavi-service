<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * What a client needs to answer a request, besides what the service gets: read with the
 * request (Client::request()), kept in the GatewayRequest, and given back to the client with
 * it (Client::response()). The service doesn't use it. NoClientState for a client without
 * any.
 */
interface ClientState {}
