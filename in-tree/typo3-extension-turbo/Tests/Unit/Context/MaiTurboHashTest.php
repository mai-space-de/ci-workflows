<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Tests\Unit\Context;

use Maispace\MaiTurbo\Context\Exception\InvalidMaiTurboContext;
use Maispace\MaiTurbo\Context\MaiTurboHash;
use PHPUnit\Framework\TestCase;

final class MaiTurboHashTest extends TestCase
{
    public function testRoundTripPreservesSecureString(): void
    {
        $hash = new MaiTurboHash('{"hello":"world"}');
        $restored = MaiTurboHash::fromUntrustedString($hash->hashedString);

        self::assertSame('{"hello":"world"}', $restored->secureString);
    }

    public function testRejectsStringWithoutSeparator(): void
    {
        $this->expectException(InvalidMaiTurboContext::class);
        MaiTurboHash::fromUntrustedString('no-separator-here');
    }

    public function testRejectsTamperedPayload(): void
    {
        $hash = new MaiTurboHash('{"hello":"world"}');
        $tampered = str_replace('world', 'evil', $hash->hashedString);

        $this->expectException(InvalidMaiTurboContext::class);
        MaiTurboHash::fromUntrustedString($tampered);
    }
}
