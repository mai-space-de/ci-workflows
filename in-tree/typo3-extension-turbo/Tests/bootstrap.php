<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$GLOBALS['TCA']['pages'] = ['ctrl' => []];
$GLOBALS['TCA']['tt_content'] = ['ctrl' => []];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'mai-turbo-test-encryption-key';
