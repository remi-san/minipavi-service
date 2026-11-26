<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Core\Context\ContextSerializer;
use MiniPaviService\Core\Routing\RouterBuilder;
use MiniPaviService\Core\Sessions;
use MiniPaviService\InvalidValueException;
use MiniPaviService\Tests\Support\StepController;
use PHPUnit\Framework\TestCase;

final class SessionsTest extends TestCase
{
    public function testThePageChangesAllowedCantBeNegative(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('The page changes allowed in one request can\'t be negative: -1');

        $router = (new RouterBuilder('form'))
            ->addRoute('form', new StepController())
            ->build();
        new Sessions(new ContextSerializer($router), $router, -1);
    }
}
