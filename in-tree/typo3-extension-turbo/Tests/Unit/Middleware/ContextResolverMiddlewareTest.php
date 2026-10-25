<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Tests\Unit\Middleware;

use Maispace\MaiTurbo\ContentObject\MaiTurboContentObject;
use Maispace\MaiTurbo\Context\ContextRecord;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Context\RenderingPath;
use Maispace\MaiTurbo\Middleware\ContextResolverMiddleware;
use Maispace\MaiTurbo\Turbo\Frame;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageArguments;

final class ContextResolverMiddlewareTest extends TestCase
{
    public function testSetsPageTypeAndRequestAttributesForValidContextHeader(): void
    {
        $context = (new MaiTurboContext(
            new RenderingPath('tt_content.mainews_list.20'),
            new ContextRecord('tt_content', 5, 7),
        ))->withAttribute('frame', new Frame('news', true, 'scope'));

        $request = new MutableServerRequest(
            new PageArguments(7, '0', [], [], []),
            $context->toHashedString(),
        );

        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new NullResponse();
            }
        };

        $cache = $this->createMock(FrontendInterface::class);
        $middleware = new ContextResolverMiddleware($cache);
        $response = $middleware->process($request, $handler);

        self::assertInstanceOf(ServerRequestInterface::class, $handler->request);
        $routing = $handler->request->getAttribute('routing');
        self::assertInstanceOf(PageArguments::class, $routing);
        self::assertSame(MaiTurboContentObject::PAGE_TYPE, $routing->getPageType());
        self::assertInstanceOf(
            MaiTurboContext::class,
            $handler->request->getAttribute(MaiTurboContext::REQUEST_ATTRIBUTE),
        );
        self::assertSame(MaiTurboContext::HEADER_NAME, $response->getHeaderLine('Vary'));
    }
}

/**
 * Minimal mutable PSR-7 request for middleware unit tests.
 */
final class MutableServerRequest implements ServerRequestInterface
{
    /** @var array<string, mixed> */
    private array $attributes;

    public function __construct(
        PageArguments $routing,
        private readonly string $contextHeader,
    ) {
        $this->attributes = ['routing' => $routing];
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function hasHeader(string $name): bool
    {
        return strcasecmp($name, MaiTurboContext::HEADER_NAME) === 0;
    }

    public function getHeader(string $name): array
    {
        return $this->hasHeader($name) ? [$this->contextHeader] : [];
    }

    public function getHeaderLine(string $name): string
    {
        return $this->hasHeader($name) ? $this->contextHeader : '';
    }

    public function withHeader(string $name, $value): static
    {
        return $this;
    }

    public function withAddedHeader(string $name, $value): static
    {
        return $this;
    }

    public function withoutHeader(string $name): static
    {
        return $this;
    }

    public function getBody(): StreamInterface
    {
        throw new \RuntimeException('not used');
    }

    public function withBody(StreamInterface $body): static
    {
        return $this;
    }

    public function getRequestTarget(): string
    {
        return '/';
    }

    public function withRequestTarget(string $requestTarget): static
    {
        return $this;
    }

    public function getMethod(): string
    {
        return 'GET';
    }

    public function withMethod(string $method): static
    {
        return $this;
    }

    public function getUri(): UriInterface
    {
        return new Uri('https://example.com/');
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        return $this;
    }

    public function getServerParams(): array
    {
        return [];
    }

    public function getCookieParams(): array
    {
        return [];
    }

    public function withCookieParams(array $cookies): static
    {
        return $this;
    }

    public function getQueryParams(): array
    {
        return [];
    }

    public function withQueryParams(array $query): static
    {
        return $this;
    }

    public function getUploadedFiles(): array
    {
        return [];
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        return $this;
    }

    public function getParsedBody(): array|object|null
    {
        return null;
    }

    public function withParsedBody($data): static
    {
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    public function withoutAttribute(string $name): static
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }
}
