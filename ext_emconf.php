<?php

$EM_CONF['dv_sso_auth'] = [
    'title' => 'SSO Auth',
    'description' => 'SSO authentication for TYPO3 CMS with Shibboleth-focused defaults.',
    'category' => 'plugin',
    'author' => 'Davitec GmbH',
    'author_email' => 'devops@davitec.de',
    'author_company' => 'Davitec GmbH, +Pluswerk Standort Dresden',
    'state' => 'stable',
    'version' => '1.0.4',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.99.99',
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
