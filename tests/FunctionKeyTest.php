<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\FunctionKey;
use PHPUnit\Framework\TestCase;

final class FunctionKeyTest extends TestCase
{
    public function testHasTheNamesTheGatewaySends(): void
    {
        // The fctn values of MiniPavi's lib/class.MiniPavi.php for the function keys
        self::assertEqualsCanonicalizing(
            ['ENVOI', 'RETOUR', 'REPETITION', 'GUIDE', 'ANNULATION', 'SOMMAIRE', 'CORRECTION', 'SUITE'],
            array_column(FunctionKey::cases(), 'value'),
        );
        self::assertSame(FunctionKey::Send, FunctionKey::tryFrom('ENVOI'));
    }

    public function testMaskIsTheOneOfMiniPaviCli(): void
    {
        self::assertSame(
            [MSK_SOMMAIRE, MSK_ANNULATION, MSK_RETOUR, MSK_REPETITION, MSK_GUIDE, MSK_CORRECTION, MSK_SUITE, MSK_ENVOI],
            [FunctionKey::Summary->mask(), FunctionKey::Cancel->mask(), FunctionKey::Previous->mask(), FunctionKey::Refresh->mask(),
                FunctionKey::Guide->mask(), FunctionKey::Correction->mask(), FunctionKey::Next->mask(), FunctionKey::Send->mask()],
        );
        self::assertSame(MSK_ENVOI | MSK_SUITE, FunctionKey::masks(FunctionKey::Send, FunctionKey::Next));
        self::assertSame(0, FunctionKey::masks());
    }

    public function testXmlNameIsTheLowercaseName(): void
    {
        foreach (FunctionKey::cases() as $key) {
            self::assertSame(strtolower($key->value), $key->xmlName());
            self::assertSame($key, FunctionKey::fromXmlName($key->xmlName()));
        }
        self::assertNull(FunctionKey::fromXmlName('enter'));
        self::assertNull(FunctionKey::fromXmlName('ENVOI'));
    }
}
