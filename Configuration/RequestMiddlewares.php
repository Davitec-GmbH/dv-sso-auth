<?php

declare(strict_types=1);

use Davitec\DvSsoAuth\Middleware\ResetBrokenFrontendSessionMiddleware;

return [
    'frontend' => [
        'davitec/dv-sso-auth/reset-broken-frontend-session' => [
            'target' => ResetBrokenFrontendSessionMiddleware::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
];
