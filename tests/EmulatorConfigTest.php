<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\EmulatorConfig;
use PHPUnit\Framework\TestCase;

final class EmulatorConfigTest extends TestCase
{
    private const VARIABLES = ['EMULATOR_URL', 'GATEWAY_URL', 'SERVICES_INTERNAL_HOST'];

    /** @var array<string, string|false> */
    private array $environment = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->environment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }
    }

    public function testPublicEmulatorOpensTheServiceAtItsUrl(): void
    {
        self::assertSame(
            'http://www.minipavi.fr/emulminitel/index.php?gw=' . urlencode(DEFAULT_MINIPAVI_GW . '/?url=' . urlencode('https://example.org/Svc/?a=1')),
            (new EmulatorConfig())->getEmulatorUrl('https://example.org/Svc/?a=1'),
        );
    }

    public function testServiceHostReplacesTheHostTheBrowserUsed(): void
    {
        $config = new EmulatorConfig('http://localhost:8081/', 'ws://localhost:8182/', 'services');

        self::assertSame(
            'http://localhost:8081/index.php?gw=' . urlencode('ws://localhost:8182/?url=' . urlencode('http://services/Svc/index.php?a=1')),
            $config->getEmulatorUrl('https://localhost:8083/Svc/index.php?a=1'),
        );
    }

    public function testInitWithoutEnvironmentUsesMiniPaviFr(): void
    {
        self::assertEquals(new EmulatorConfig(), EmulatorConfig::init());
    }

    public function testInitWithALocalEmulatorReachesTheServicesHost(): void
    {
        putenv('EMULATOR_URL=http://localhost:8081');
        putenv('GATEWAY_URL=ws://localhost:8182');

        self::assertEquals(new EmulatorConfig('http://localhost:8081', 'ws://localhost:8182', 'services'), EmulatorConfig::init());

        putenv('SERVICES_INTERNAL_HOST=web');
        self::assertEquals(new EmulatorConfig('http://localhost:8081', 'ws://localhost:8182', 'web'), EmulatorConfig::init());
    }
}
