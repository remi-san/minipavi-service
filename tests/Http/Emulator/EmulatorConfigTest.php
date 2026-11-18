<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http\Emulator;

use MiniPaviService\Http\Emulator\EmulatorConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmulatorConfigTest extends TestCase
{
    public function testPublicEmulatorOpensTheServiceAtItsUrl(): void
    {
        self::assertSame(
            'http://www.minipavi.fr/emulminitel/index.php?gw='
                . urlencode('wss://go.minipavi.fr:8181/?url=' . urlencode('https://example.org/Svc/?a=1')),
            (new EmulatorConfig())->emulatorUrl('https://example.org/Svc/?a=1'),
        );
    }

    public function testServiceHostReplacesTheHostTheBrowserUsed(): void
    {
        $config = new EmulatorConfig('http://localhost:8081/', 'ws://localhost:8182/', 'services');

        self::assertSame(
            'http://localhost:8081/index.php?gw='
                . urlencode('ws://localhost:8182/?url=' . urlencode('http://services/Svc/index.php?a=1')),
            $config->emulatorUrl('https://localhost:8083/Svc/index.php?a=1'),
        );
    }

    /**
     * The values of the environment, as the container reads them (getenv() ?: null)
     *
     * @return iterable<string, array{?string, ?string, ?string, EmulatorConfig}>
     */
    public static function environments(): iterable
    {
        yield 'none: minipavi.fr' => [null, null, null, new EmulatorConfig()];
        yield 'empty: minipavi.fr' => ['', '', '', new EmulatorConfig()];
        yield 'a local emulator: the services host' => [
            'http://localhost:8081',
            'ws://localhost:8182',
            null,
            new EmulatorConfig('http://localhost:8081', 'ws://localhost:8182', 'services'),
        ];
        yield 'another services host' => [
            'http://localhost:8081',
            'ws://localhost:8182',
            'web',
            new EmulatorConfig('http://localhost:8081', 'ws://localhost:8182', 'web'),
        ];
    }

    #[DataProvider('environments')]
    public function testFromTheValuesOfTheEnvironment(
        ?string $emulatorUrl,
        ?string $gatewayUrl,
        ?string $servicesHost,
        EmulatorConfig $config,
    ): void {
        self::assertEquals($config, EmulatorConfig::fromValues($emulatorUrl, $gatewayUrl, $servicesHost));
    }
}
