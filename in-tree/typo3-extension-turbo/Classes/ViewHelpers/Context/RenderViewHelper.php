<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\ViewHelpers\Context;

use Maispace\MaiTurbo\Compatibility\ServerRequestFromRenderingContext;
use Maispace\MaiTurbo\ContentObject\MaiTurboContentObject;
use Maispace\MaiTurbo\Context\Attribute\Plugin;
use Maispace\MaiTurbo\Context\ContextStack;
use Maispace\MaiTurbo\Context\Exception\InvalidMaiTurboContext;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class RenderViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    /**
     * @throws InvalidMaiTurboContext
     */
    public function render(): string
    {
        $context = (new ContextStack($this->renderingContext->getViewHelperVariableContainer()))->current();
        if (!$context instanceof MaiTurboContext) {
            throw new InvalidMaiTurboContext(
                'Can only render as child of a Mai Turbo context view helper',
                1671623956,
            );
        }
        assert($this->renderingContext instanceof RenderingContext);
        $request = (new ServerRequestFromRenderingContext($this->renderingContext))
            ->getRequest()
            ->withAttribute(MaiTurboContext::REQUEST_ATTRIBUTE, $context);
        $actionRequest = self::addActionNameToRequest($request, $context);
        $contentData = [];
        if ($request !== $actionRequest) {
            $contentData = [
                'maiTurbo' => [
                    'context' => $context,
                    'routing' => $actionRequest->getAttribute('routing'),
                ],
            ];
        }
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->start(
            $contentData,
            $context->contextRecord->tableName,
        );
        $contentObjectRenderer->setRequest($actionRequest);
        $contentObjectRenderer->currentRecord = $context->contextRecord->tableName
            . ':'
            . $context->contextRecord->id;

        return $contentObjectRenderer->cObjGetSingle(
            MaiTurboContentObject::NAME,
            [
                'context' => $context,
            ],
        );
    }

    private static function addActionNameToRequest(
        ServerRequestInterface $request,
        MaiTurboContext $context,
    ): ServerRequestInterface {
        $plugin = $context->getAttribute('plugin');
        if (
            !$plugin instanceof Plugin
            || $plugin->actionName === null
        ) {
            return $request;
        }
        $pageArguments = $request->getAttribute('routing');
        if (!$pageArguments instanceof PageArguments) {
            return $request;
        }

        $routeArguments = $pageArguments->getRouteArguments();
        $routeArguments[$plugin->pluginNamespace] ??= [];
        assert(is_array($routeArguments[$plugin->pluginNamespace]));
        $routeArguments[$plugin->pluginNamespace]['action'] = $plugin->actionName;

        $modifiedPageArguments = new PageArguments(
            $pageArguments->getPageId(),
            $pageArguments->getPageType(),
            $routeArguments,
            $pageArguments->getStaticArguments(),
            $pageArguments->getDynamicArguments(),
        );

        return $request->withAttribute('routing', $modifiedPageArguments);
    }
}
