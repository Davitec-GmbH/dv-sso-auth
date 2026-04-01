<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../../vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

spl_autoload_register(static function (string $className): void {
    $prefixes = [
        'Davitec\\DvSsoAuth\\Tests\\' => __DIR__ . '/',
        'Davitec\\DvSsoAuth\\' => dirname(__DIR__) . '/Classes/',
    ];

    foreach ($prefixes as $prefix => $baseDirectory) {
        if (!str_starts_with($className, $prefix)) {
            continue;
        }

        $relativeClass = substr($className, strlen($prefix));
        if ($relativeClass === false) {
            continue;
        }

        $path = $baseDirectory . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($path)) {
            require_once $path;
        }

        return;
    }
});
