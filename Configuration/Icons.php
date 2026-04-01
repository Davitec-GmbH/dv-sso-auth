<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'loginIcon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:dv_sso_auth/Resources/Public/Icons/Backend/sign-in-alt-solid.svg',
    ],
];
