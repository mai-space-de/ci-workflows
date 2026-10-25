<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\EventListener;

use Maispace\MaiTurbo\Context\ContextRecord;
use Maispace\MaiTurbo\Context\MaiTurboContext;
use Maispace\MaiTurbo\Context\MaiTurboContextFactory;
use Maispace\MaiTurbo\Exception\InvalidConfiguration;
use Maispace\MaiTurbo\Turbo\Frame;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\ModifyPageLinkConfigurationEvent;

class PageLinkModifier
{
    private const VIRTUAL_LINK_NAMESPACE = 'maiTurbo';
    private const CONFIG_NAMESPACE = self::VIRTUAL_LINK_NAMESPACE . '.';
    private const LINK_NAMESPACE = MaiTurboContext::ARGUMENT_NAME;

    public function modifyQueryParameters(ModifyPageLinkConfigurationEvent $linkConfigurationEvent): void
    {
        $linkConfiguration = $linkConfigurationEvent->getConfiguration();
        $queryParameters = $linkConfigurationEvent->getQueryParameters();
        if (
            !isset($queryParameters[self::VIRTUAL_LINK_NAMESPACE])
            && !isset($linkConfiguration[self::CONFIG_NAMESPACE])
        ) {
            return;
        }
        $request = $linkConfigurationEvent->getRequest();
        $contentObjectRenderer = $request->getAttribute('currentContentObject');
        if (!$contentObjectRenderer instanceof ContentObjectRenderer) {
            return;
        }
        $queryParameters = $this->buildQueryParameters(
            $contentObjectRenderer,
            $linkConfiguration,
            $queryParameters,
            $linkConfigurationEvent->getLinkDetails()['pageuid'] ?? null,
        );
        $linkConfigurationEvent->setQueryParameters($queryParameters);
    }

    /**
     * @param array<mixed> $linkConfiguration
     * @param array<mixed> $queryParameters
     * @return array<mixed>
     */
    private function buildQueryParameters(
        ContentObjectRenderer $contentObjectRenderer,
        array $linkConfiguration,
        array $queryParameters,
        int|string|null $contextPageId = null,
    ): array {
        $contextPageId = MathUtility::canBeInterpretedAsInteger($contextPageId) ? (int) $contextPageId : null;
        $context = $this->resolveContext(
            $contentObjectRenderer,
            $linkConfiguration,
            $queryParameters,
            $contextPageId,
        );
        unset($queryParameters[self::VIRTUAL_LINK_NAMESPACE]);
        $queryParameters[self::LINK_NAMESPACE] = $context->toHashedString();

        return $queryParameters;
    }

    /**
     * @param array<mixed> $linkConfiguration
     * @param array<mixed> $queryParameters
     */
    private function resolveContext(
        ContentObjectRenderer $contentObjectRenderer,
        array $linkConfiguration,
        array $queryParameters,
        ?int $contextPageId = null,
    ): MaiTurboContext {
        $maiTurboArguments = $this->resolveMaiTurboArguments($linkConfiguration, $queryParameters);
        assert(in_array($maiTurboArguments['type'], ['plugin', 'contentElement', 'typoScript', 'context'], true));
        $contextRecordId = $contentObjectRenderer->currentRecord;
        if (isset($maiTurboArguments['tableName'], $maiTurboArguments['recordUid'])) {
            $contextRecordId = $maiTurboArguments['tableName'] . ':' . $maiTurboArguments['recordUid'];
        }
        $contextFactory = new MaiTurboContextFactory($contentObjectRenderer->getRequest());
        $context = match ($maiTurboArguments['type']) {
            'context' => $this->resolveFromRequest($contentObjectRenderer->getRequest(), $contextPageId),
            'plugin' => $contextFactory->forPlugin(
                $maiTurboArguments['extensionName'],
                $maiTurboArguments['pluginName'],
                $contextRecordId,
                $contextPageId,
            ),
            'contentElement' => $contextFactory->forPath(
                'tt_content',
                'tt_content:' . $maiTurboArguments['uid'],
            ),
            'typoScript' => $contextFactory->forPath(
                $maiTurboArguments['typoScriptPath'],
                $contextRecordId,
                $contextPageId,
            ),
        };
        $frame = new Frame(
            baseId: $maiTurboArguments['frameId'] ?? 'link',
            wrapResponse: isset($maiTurboArguments['wrapResponse']) && (bool) (int) $maiTurboArguments['wrapResponse'],
            scope: $context->scope,
        );

        return $context->withAttribute('frame', $frame);
    }

    private function resolveFromRequest(ServerRequestInterface $request, ?int $contextPageId): MaiTurboContext
    {
        $context = $request->getAttribute(MaiTurboContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof MaiTurboContext) {
            throw new InvalidConfiguration(
                'Mai Turbo type was set to "context", but no context could be resolved',
                1676815069,
            );
        }
        if ($contextPageId === null) {
            return $context;
        }

        return $context->withContextRecord(new ContextRecord(
            $context->contextRecord->tableName,
            $context->contextRecord->id,
            $contextPageId,
        ));
    }

    /**
     * @param array<mixed> $linkConfiguration
     * @param array<mixed> $queryParameters
     * @return array<mixed>
     */
    private function resolveMaiTurboArguments(array $linkConfiguration, array $queryParameters): array
    {
        if (isset($linkConfiguration[self::CONFIG_NAMESPACE])) {
            return $this->ensureValidArguments($linkConfiguration[self::CONFIG_NAMESPACE]);
        }
        if (isset($queryParameters[self::VIRTUAL_LINK_NAMESPACE])) {
            return $this->ensureValidArguments($queryParameters[self::VIRTUAL_LINK_NAMESPACE]);
        }
        throw new \LogicException('Mai Turbo link requested, but no link configuration is present', 1691582649);
    }

    /**
     * @param array<mixed> $maiTurboArguments
     * @return array<mixed>
     */
    private function ensureValidArguments(array $maiTurboArguments): array
    {
        try {
            match ($maiTurboArguments['type'] ?? null) {
                'context' => true,
                'plugin' => !isset($maiTurboArguments['extensionName'], $maiTurboArguments['pluginName'])
                    && throw new InvalidConfiguration(
                        'URLs of type "plugin" must have "extensionName" and "pluginName" set',
                        1671558884,
                    ),
                'contentElement' => !isset($maiTurboArguments['uid'])
                    && throw new InvalidConfiguration(
                        'URLs of type "contentElement" must have "uid" set',
                        1671560042,
                    ),
                'typoScript' => !isset($maiTurboArguments['typoScriptPath'])
                    && throw new InvalidConfiguration(
                        'URLs of type "typoScript" must have "typoScriptPath" set',
                        1671558886,
                    ),
            };
        } catch (\UnhandledMatchError $e) {
            throw new InvalidConfiguration(
                'URL type must be set and must be either "plugin", "contentElement", "typoScript" or "context"',
                1671560111,
                $e,
            );
        }

        return $maiTurboArguments;
    }
}
