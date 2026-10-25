<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Context;

use Maispace\MaiTurbo\Context\Exception\InvalidMaiTurboContext;

class MaiTurboHash
{
    private const HASH_SEPARATOR = '::hash::';

    public readonly string $hashedString;

    public function __construct(public readonly string $secureString)
    {
        $this->hashedString = $this->secureString
            . self::HASH_SEPARATOR
            . self::calculateHmac($this->secureString);
    }

    public static function fromUntrustedString(string $untrustedString): self
    {
        if (!str_contains($untrustedString, self::HASH_SEPARATOR)) {
            throw new InvalidMaiTurboContext('No hmac submitted', 1671485145);
        }
        [$securedString, $hash] = explode(self::HASH_SEPARATOR, $untrustedString);
        if (!hash_equals(self::calculateHmac($securedString), $hash)) {
            throw new InvalidMaiTurboContext('Hmac mismatch', 1671485170);
        }

        return new self($securedString);
    }

    private static function calculateHmac(string $secureString): string
    {
        return hash_hmac(
            'sha1',
            $secureString,
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . self::class,
        );
    }
}
