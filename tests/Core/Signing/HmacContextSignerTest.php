<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core\Signing;

use MiniPaviService\Core\Signing\ForgedContextException;
use MiniPaviService\Core\Signing\HmacContextSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HmacContextSignerTest extends TestCase
{
    private const CONTEXT = '{"route":"directory","states":{},"parameters":{"directory":{"page":2}}}';

    public function testGivesBackTheContextItSigned(): void
    {
        $signer = new HmacContextSigner('secret');

        self::assertSame(self::CONTEXT, $signer->verify($signer->sign(self::CONTEXT)));
    }

    public function testSignsWithAnUnpaddedBase64UrlSignature(): void
    {
        self::assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]{43}\.\{"route"/',
            (new HmacContextSigner('secret'))->sign(self::CONTEXT),
        );
    }

    public function testGivesBackAnEmptyContext(): void
    {
        $signer = new HmacContextSigner('secret');

        self::assertSame('', $signer->verify($signer->sign('')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forged(): iterable
    {
        $signed = (new HmacContextSigner('secret'))->sign(self::CONTEXT);
        [$signature] = explode('.', $signed, limit: 2);

        yield 'unsigned' => [self::CONTEXT];
        yield 'empty' => [''];
        yield 'other context' => [
            $signature . '.' . str_replace('"page":2', replace: '"page":99999', subject: self::CONTEXT),
        ];
        yield 'other signature' => ['A' . substr($signed, offset: 1)];
        yield 'other key' => [(new HmacContextSigner('other'))->sign(self::CONTEXT)];
    }

    #[DataProvider('forged')]
    public function testRefusesAContextItDidNotSign(string $forged): void
    {
        $this->expectException(ForgedContextException::class);

        (new HmacContextSigner('secret'))->verify($forged);
    }

    public function testRefusesAnEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HmacContextSigner('');
    }
}
