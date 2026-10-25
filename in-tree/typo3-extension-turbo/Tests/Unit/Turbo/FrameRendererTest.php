<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Tests\Unit\Turbo;

use Maispace\MaiTurbo\Context\ContextRecord;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Context\RenderingPath;
use Maispace\MaiTurbo\Turbo\Frame;
use Maispace\MaiTurbo\Turbo\FrameOptions;
use Maispace\MaiTurbo\Turbo\FrameRenderer;
use PHPUnit\Framework\TestCase;

final class FrameRendererTest extends TestCase
{
    public function testRendersTurboFrameWithContextAttribute(): void
    {
        $context = new MaiTurboContext(
            new RenderingPath('tt_content'),
            new ContextRecord('pages', 1, 1),
        );
        $frame = new Frame('list', false, $context->scope);
        $html = (new FrameRenderer())->render(
            frame: $frame,
            content: '<p>content</p>',
            options: new FrameOptions(src: '/async'),
            context: $context,
        );

        self::assertStringContainsString('<turbo-frame', $html);
        self::assertStringContainsString('id="' . $frame->id . '"', $html);
        self::assertStringContainsString('data-mai-turbo-context="', $html);
        self::assertStringContainsString('src="/async"', $html);
        self::assertStringContainsString('<p>content</p>', $html);
        self::assertStringContainsString('</turbo-frame>', $html);
    }

    public function testFrameIdIncludesScopeWhenPresent(): void
    {
        $frame = new Frame('news', true, 'abc123');
        self::assertSame('news__abc123', $frame->id);
    }
}
