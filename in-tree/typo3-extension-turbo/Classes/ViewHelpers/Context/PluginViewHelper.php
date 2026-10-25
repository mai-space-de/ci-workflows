<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\ViewHelpers\Context;

use Maispace\MaiTurbo\Compatibility\ServerRequestFromRenderingContext;
use Maispace\MaiTurbo\Context\Attribute\Section;
use Maispace\MaiTurbo\Context\ContextStack;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Context\MaiTurboContextFactory;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class PluginViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;
    protected $escapeChildren = true;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'extensionName',
            'string',
            'Target Extension Name (without `tx_` prefix and no underscores). If empty, the current extension name is used',
        );
        $this->registerArgument(
            'pluginName',
            'string',
            'Target plugin. If empty, the current plugin name is used',
        );
        $this->registerArgument(
            'action',
            'string',
            'Target action. If empty, the current action is used. Relevant when using context.render as a child',
        );
        $this->registerArgument(
            'section',
            'string',
            'Fluid section to render only. Controllers must respect this attribute when used with context.render',
        );
        $this->registerArgument(
            'pageUid',
            'int',
            'Uid of the page on which the plugin will be rendered. If empty, the current page uid is used',
        );
    }

    public function render(): string
    {
        $requestFromRenderingContext = new ServerRequestFromRenderingContext($this->renderingContext);
        $request = $requestFromRenderingContext->getRequest();

        $contextFactory = new MaiTurboContextFactory($request);
        $context = $contextFactory->forArguments($this->arguments);
        if (isset($this->arguments['section'])) {
            $context = $context->withAttribute('section', new Section($this->arguments['section']));
        }

        $contextStack = new ContextStack($this->renderingContext->getViewHelperVariableContainer());
        $contextStack->push($context);

        $contentObject = $request->getAttribute('currentContentObject');

        $maiTurboRequest = $request->withAttribute(MaiTurboContext::REQUEST_ATTRIBUTE, $context);
        $contentObject?->setRequest($maiTurboRequest);
        $requestFromRenderingContext->setRequest($maiTurboRequest);
        $renderedChildren = $this->renderChildren();
        $requestFromRenderingContext->setRequest($request);
        $contentObject?->setRequest($request);
        $contextStack->pop();

        return (string) $renderedChildren;
    }
}
