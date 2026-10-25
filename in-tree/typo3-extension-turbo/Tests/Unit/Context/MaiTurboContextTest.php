<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Tests\Unit\Context;

use Maispace\MaiTurbo\Context\ContextDenormalizer;
use Maispace\MaiTurbo\Context\ContextRecord;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Context\RenderingPath;
use Maispace\MaiTurbo\Turbo\Frame;
use PHPUnit\Framework\TestCase;

final class MaiTurboContextTest extends TestCase
{
    public function testHashedStringRoundTripRestoresContextAndFrame(): void
    {
        $context = (new MaiTurboContext(
            new RenderingPath('tt_content.mainews_list.20'),
            new ContextRecord('tt_content', 42, 10),
        ))->withAttribute('frame', new Frame('news-list', true, 'scope1'));

        $restored = MaiTurboContext::fromUntrustedString(
            $context->toHashedString(),
            new ContextDenormalizer(),
        );

        self::assertSame('tt_content.mainews_list.20', $restored->renderingPath->jsonSerialize());
        self::assertSame('tt_content', $restored->contextRecord->tableName);
        self::assertSame(42, $restored->contextRecord->id);
        self::assertSame(10, $restored->contextRecord->pageId);

        $frame = $restored->getAttribute('frame');
        self::assertInstanceOf(Frame::class, $frame);
        self::assertSame('news-list__scope1', $frame->id);
        self::assertTrue($frame->wrapResponse);
    }

    public function testScopeIsStableForSamePathAndRecord(): void
    {
        $a = new MaiTurboContext(
            new RenderingPath('tt_content'),
            new ContextRecord('pages', 1, 1),
        );
        $b = new MaiTurboContext(
            new RenderingPath('tt_content'),
            new ContextRecord('pages', 1, 1),
        );

        self::assertSame($a->scope, $b->scope);
    }
}
