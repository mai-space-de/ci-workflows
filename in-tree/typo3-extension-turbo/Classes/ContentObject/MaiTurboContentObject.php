<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\ContentObject;

use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Turbo\Frame;
use Maispace\MaiTurbo\Turbo\FrameRenderer;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;

class MaiTurboContentObject extends AbstractContentObject
{
    public const NAME = 'MAITURBO';
    public const PAGE_TYPE = '2026072714';

    /**
     * @param array<mixed> $conf
     */
    public function render($conf = []): string
    {
        $context = $conf['context'] ?? null;
        if ($context === 'fromRequest') {
            $context = $this->request->getAttribute(MaiTurboContext::REQUEST_ATTRIBUTE);
        }
        assert($context instanceof MaiTurboContext);
        $content = $this->getContentObjectRenderer()->cObjGetSingle(
            'RECORDS',
            $this->transformToRecordsConfiguration($context),
        );
        $frame = $context->getAttribute('frame');
        if (
            !$frame instanceof Frame
            || !$frame->wrapResponse
            || !MaiTurboContext::isRequestSubmitted($this->request)
        ) {
            return $content;
        }

        return (new FrameRenderer())->render(
            frame: $frame,
            content: $content,
            context: $context,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformToRecordsConfiguration(MaiTurboContext $context): array
    {
        return [
            'source' => $context->contextRecord->tableName . '_' . $context->contextRecord->id,
            'tables' => $context->contextRecord->tableName,
            'dontCheckPid' => $context->contextRecord->tableName === 'pages',
            'conf.' => [
                $context->contextRecord->tableName => '< ' . $context->renderingPath->jsonSerialize(),
            ],
        ];
    }
}
