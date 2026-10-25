<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Compatibility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Resolves the frontend request from a Fluid rendering context (TYPO3 14).
 */
class ServerRequestFromRenderingContext
{
    private readonly RenderingContext $renderingContext;

    public function __construct(RenderingContextInterface $renderingContext)
    {
        if (!$renderingContext instanceof RenderingContext) {
            throw new \InvalidArgumentException(
                'Expected TYPO3 Fluid RenderingContext',
                1753600001,
            );
        }
        $this->renderingContext = $renderingContext;
    }

    public function getRequest(): ServerRequestInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);

        return $request;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->renderingContext->setAttribute(ServerRequestInterface::class, $request);
    }
}
