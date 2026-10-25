<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Middleware;

use Maispace\MaiTurbo\ContentObject\MaiTurboContentObject;
use Maispace\MaiTurbo\Context\ContextDenormalizer;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Turbo\Frame;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Routing\PageArguments;

class ContextResolverMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly FrontendInterface $cache) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = null;
        $contextString = $request->getQueryParams()[MaiTurboContext::ARGUMENT_NAME]
            ?? $request->getHeaderLine(MaiTurboContext::HEADER_NAME);
        if ($contextString !== '') {
            $context = MaiTurboContext::fromUntrustedString($contextString, new ContextDenormalizer());
        }
        $pageArguments = $request->getAttribute('routing');
        if (
            !$context instanceof MaiTurboContext
            || !$pageArguments instanceof PageArguments
        ) {
            return $this->addVaryHeader(
                $handler->handle($request->withAttribute(MaiTurboContext::REQUEST_ATTRIBUTE, null)),
            );
        }
        $cacheId = $context->cacheId;
        $frame = $context->getAttribute('frame');
        $pageType = MaiTurboContentObject::PAGE_TYPE;
        if ($context->contextRecord->pageId !== $pageArguments->getPageId()) {
            if (!$this->isPageBoundaryCrossingAllowed($context, $request)) {
                return $this->addVaryHeader(
                    $handler->handle($request->withAttribute(MaiTurboContext::REQUEST_ATTRIBUTE, null)),
                );
            }
            $context = null;
            $pageType = '0';
        }
        $newStaticArguments = array_merge(
            $pageArguments->getStaticArguments(),
            [
                MaiTurboContext::ARGUMENT_NAME => $cacheId,
            ],
        );
        $newDynamicArguments = $pageArguments->getDynamicArguments();
        $frameAttribute = $context?->getAttribute('frame');
        if ($frameAttribute instanceof Frame && $frameAttribute->renderFullDocument) {
            $newDynamicArguments[MaiTurboContext::ARGUMENT_NAME_DOCUMENT] = 'true';
        }
        $modifiedPageArguments = new PageArguments(
            $pageArguments->getPageId(),
            $pageType,
            $pageArguments->getRouteArguments(),
            $newStaticArguments,
            $newDynamicArguments,
        );
        $request = $request
            ->withAttribute('routing', $modifiedPageArguments)
            ->withAttribute(MaiTurboContext::REQUEST_ATTRIBUTE, $context)
            ->withAttribute(MaiTurboContext::REQUEST_ATTRIBUTE_FRAME, $frame);

        return $this->addVaryHeader($this->trackPageBoundaries($handler->handle($request), $context));
    }

    private function addVaryHeader(ResponseInterface $response): ResponseInterface
    {
        $varyHeader = $response->getHeader('Vary');
        $varyHeader[] = MaiTurboContext::HEADER_NAME;

        return $response->withAddedHeader('Vary', $varyHeader);
    }

    private function isPageBoundaryCrossingAllowed(MaiTurboContext $context, RequestInterface $request): bool
    {
        if (Environment::getContext()->isDevelopment()) {
            return true;
        }
        $allowedUris = $this->cache->get($this->getCacheIdentifier($context));
        $allowedUris = $allowedUris === false ? [] : $allowedUris;

        return isset($allowedUris[(string) $request->getUri()]);
    }

    private function trackPageBoundaries(ResponseInterface $response, ?MaiTurboContext $context): ResponseInterface
    {
        if (!$context instanceof MaiTurboContext || $response->getHeaderLine('Location') === '') {
            return $response;
        }
        $cacheIdentifier = $this->getCacheIdentifier($context);
        $allowedUris = $this->cache->get($cacheIdentifier);
        $allowedUris = $allowedUris === false ? [] : $allowedUris;
        $allowedUris[$response->getHeaderLine('Location')] = true;
        $this->cache->set(
            $cacheIdentifier,
            $allowedUris,
            [
                'pageId_' . $context->contextRecord->pageId,
            ],
        );

        return $response;
    }

    private function getCacheIdentifier(MaiTurboContext $context): string
    {
        return 'mai_turbo_' . $context->contextRecord->pageId;
    }
}
