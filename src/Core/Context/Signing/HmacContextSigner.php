<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Context\Signing;

/**
 * Signs the context with an HMAC-SHA256 of a secret key: "<signature>.<context>", the
 * signature in unpadded base64url (43 characters). Only who has the key can sign a context:
 * keep it out of the repository (e.g. in the environment, CONTEXT_SECRET). Changing it makes
 * the contexts of sessions in progress forged: their users start again at the default route.
 */
final readonly class HmacContextSigner implements ContextSigner
{
    private const ALGORITHM = 'sha256';

    private const SEPARATOR = '.';

    /**
     * @param string $key secret key, e.g. 32 random bytes (`openssl rand -base64 32`)
     *
     * @throws \InvalidArgumentException for an empty key
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $key,
    ) {
        if ($key === '') {
            throw new \InvalidArgumentException('The key signing the context is empty');
        }
    }

    public function sign(string $context): string
    {
        return $this->signature($context) . self::SEPARATOR . $context;
    }

    public function verify(string $signed): string
    {
        $parts = explode(self::SEPARATOR, $signed, limit: 2);
        if (\count($parts) !== 2) {
            throw new ForgedContextException('Context without signature');
        }
        [$signature, $context] = $parts;
        if (!hash_equals($this->signature($context), $signature)) {
            throw new ForgedContextException('Context with a wrong signature');
        }

        return $context;
    }

    private function signature(string $context): string
    {
        return rtrim(
            strtr(base64_encode(hash_hmac(self::ALGORITHM, $context, $this->key, binary: true)), from: '+/', to: '-_'),
            characters: '=',
        );
    }
}
