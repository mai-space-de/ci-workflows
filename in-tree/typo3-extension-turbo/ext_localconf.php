<?php

declare(strict_types=1);

use Maispace\MaiTurbo\Context\MaiTurboContext;

(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = MaiTurboContext::ARGUMENT_NAME;
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = MaiTurboContext::ARGUMENT_NAME_DOCUMENT;
})();
