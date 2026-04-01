<?php

$EM_CONF['dv_sso_auth'] = [
    'title' => 'dv_sso_auth',
    'description' => 'SSO authentication for TYPO3 CMS with Shibboleth-focused defaults.',
    'category' => 'plugin',
    'author' => 'Davitec GmbH',
    'author_email' => 'info@davitec.de',
    'author_company' => 'Davitec GmbH',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'php' => '8.1.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Davitec\\DvSsoAuth\\' => 'Classes',
        ],
    ],
];
