<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Http\Kernel;
use MiniPaviService\Http\ResponseEmitter;
use MiniPaviService\Tests\Support\RecordingHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * The only place reading the globals and writing the response: in separate processes
 */
final class KernelTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testGivesTheHandlerTheRequestOfTheGlobals(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/Svc/?web=true&route=games';
        $_SERVER['QUERY_STRING'] = 'web=true&route=games';
        $_SERVER['PHP_SELF'] = '/Svc/index.php';
        $_GET = ['web' => 'true', 'route' => 'games'];
        $handler = new RecordingHandler('page');

        ob_start();
        try {
            self::kernel($handler)->run();
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame('page', $output);
        $request = $handler->requests[0];
        self::assertSame('Mozilla/5.0', $request->getHeaderLine('User-Agent'));
        self::assertSame(['web' => 'true', 'route' => 'games'], $request->getQueryParams());
        self::assertSame('/Svc/index.php', $request->getServerParams()['PHP_SELF'] ?? null);
    }

    private static function kernel(RequestHandlerInterface $handler): Kernel
    {
        $psr17 = new Psr17Factory();

        return new Kernel($handler, new PsrHttpFactory($psr17, $psr17, $psr17, $psr17), new ResponseEmitter());
    }
}
