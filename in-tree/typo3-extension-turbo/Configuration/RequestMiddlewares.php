<?php

declare(strict_types=1);

use Maispace\MaiTurbo\Middleware\ContextResolverMiddleware;

return [
    'frontend' => [
        'maispace/mai-turbo/context-resolver' => [
            'target' => ContextResolverMiddleware::class,
            'after' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'before' => [
                'typo3/cms-frontend/tsfe',
                'maispace/mai-assets/static-file-serve',
            ],
        ],
    ],
];
