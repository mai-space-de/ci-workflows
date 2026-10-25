<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\ViewHelpers;

use Maispace\MaiTurbo\Compatibility\ServerRequestFromRenderingContext;
use Maispace\MaiTurbo\Context\Attribute\Plugin;
use Maispace\MaiTurbo\Context\ContextStack;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Turbo\Frame;
use Maispace\MaiTurbo\Turbo\FrameOptions;
use Maispace\MaiTurbo\Turbo\FrameRenderer;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class FrameViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('id', 'string', 'id of the frame', true);
        $this->registerArgument(
            'src',
            'string',
            'Either the keyword "async", which uses the current Mai Turbo context when fetching HTML asynchronously, or any absolute/relative URL',
        );
        $this->registerArgument(
            'wrapResponse',
            'bool',
            'Whether to wrap the response of the content in this frame',
            false,
            false,
        );
        $this->registerArgument(
            'propagateUrl',
            'bool',
            'Whether the URL should be pushed to browser history',
            false,
            false,
        );
        $this->registerArgument('target', 'string', 'Turbo target for links and forms within this frame');
        $this->registerArgument(
            'additionalAttributes',
            'array',
            'Additional attributes for the turbo-frame tag',
            false,
            [],
        );
    }

    public function render(): string
    {
        assert($this->renderingContext instanceof RenderingContext);
        $stack = new ContextStack($this->renderingContext->getViewHelperVariableContainer());
        $context = $stack->current();
        $frame = new Frame(
            baseId: $this->arguments['id'],
            wrapResponse: $this->arguments['wrapResponse'],
            scope: $context?->scope,
            renderFullDocument: $this->arguments['propagateUrl'],
        );
        if (isset($context)) {
            $context = $context->withAttribute('frame', $frame);
            $stack->push($context);
        }
        $content = $this->renderChildren();
        if (isset($context)) {
            $stack->pop();
        }
        if ($content === null) {
            return $frame->id;
        }
        $request = (new ServerRequestFromRenderingContext($this->renderingContext))->getRequest();

        return (new FrameRenderer())->render(
            frame: $frame,
            content: $content,
            options: new FrameOptions(
                src: $this->extractSourceUrl($this->arguments, $request, $context),
                target: $this->arguments['target'],
                propagateUrl: $this->arguments['propagateUrl'],
                additionalAttributes: $this->arguments['additionalAttributes'],
            ),
            context: $context,
        );
    }

    /**
     * @param array<mixed> $arguments
     */
    private function extractSourceUrl(
        array $arguments,
        ServerRequestInterface $request,
        ?MaiTurboContext $context,
    ): ?string {
        if (!isset($context, $arguments['src'])) {
            return null;
        }
        if ($arguments['src'] !== 'async') {
            return $arguments['src'];
        }
        $linkArguments = [];
        $pluginAttribute = $context->getAttribute('plugin');
        if ($pluginAttribute instanceof Plugin && $pluginAttribute->actionName !== null) {
            $linkArguments[$pluginAttribute->pluginNamespace] = [
                'action' => $pluginAttribute->actionName,
            ];
        }
        $site = $request->getAttribute('site');
        assert($site instanceof Site);

        return (string) $site->getRouter()->generateUri($context->contextRecord->pageId, $linkArguments);
    }
}
