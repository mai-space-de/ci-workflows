<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Turbo;

use Maispace\MaiTurbo\Context\MaiTurboContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class FrameRenderer
{
    public function render(
        Frame $frame,
        string $content,
        ?FrameOptions $options = null,
        ?MaiTurboContext $context = null,
    ): string {
        $tagBuilder = new TagBuilder('turbo-frame', $content);
        $tagBuilder->addAttribute('id', $frame->id);
        if ($context instanceof MaiTurboContext) {
            $tagBuilder->addAttribute('data-mai-turbo-context', $context->toHashedString());
        }
        if ($options?->propagateUrl === true) {
            $tagBuilder->addAttribute('data-turbo-action', 'advance');
        }
        if (isset($options->src) && $options->src !== '') {
            $tagBuilder->addAttribute('src', $options->src);
        }
        if (isset($options->target) && $options->target !== '') {
            $tagBuilder->addAttribute('target', $options->target);
        }
        if (isset($options->additionalAttributes) && $options->additionalAttributes !== []) {
            foreach ($options->additionalAttributes as $name => $value) {
                $tagBuilder->addAttribute($name, $value);
            }
        }

        return $tagBuilder->render();
    }
}
