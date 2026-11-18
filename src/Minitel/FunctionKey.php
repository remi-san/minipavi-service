<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

use MiniPaviService\Gateway\ConnectionCode;

/**
 * Function keys of the Minitel, by the name the gateway sends (Request::key()), with their
 * mask in input commands (MSK_* of MiniPaviCli) and their name in the page XML format
 * (validwith="envoi suite")
 */
enum FunctionKey: string implements Key
{
    case Summary = 'SOMMAIRE';
    case Cancel = 'ANNULATION';
    case Previous = 'RETOUR';
    case Refresh = 'REPETITION';
    case Guide = 'GUIDE';
    case Correction = 'CORRECTION';
    case Next = 'SUITE';
    case Send = 'ENVOI';

    /**
     * The key itself: it is a function key
     */
    public function functionKey(): self
    {
        return $this;
    }

    /**
     * Null: a function key isn't a connection code
     */
    public function connectionCode(): ?ConnectionCode
    {
        return null;
    }

    /**
     * Key of a name of the page XML format (envoi, suite...), null if there is none
     */
    public static function fromXmlName(string $name): ?self
    {
        foreach (self::cases() as $key) {
            if ($key->xmlName() === $name) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Mask of keys, for the validWith of input commands
     */
    public static function masks(self ...$keys): int
    {
        return array_reduce($keys, static fn(int $mask, self $key) => $mask | $key->mask(), initial: 0);
    }

    /**
     * Bit of the key in the validWith of input commands (MSK_* of MiniPaviCli)
     */
    public function mask(): int
    {
        return match ($this) {
            self::Summary => 1,
            self::Cancel => 2,
            self::Previous => 4,
            self::Refresh => 8,
            self::Guide => 16,
            self::Correction => 32,
            self::Next => 64,
            self::Send => 128,
        };
    }

    /**
     * Name in the page XML format: envoi, suite...
     */
    public function xmlName(): string
    {
        return strtolower($this->value);
    }
}
