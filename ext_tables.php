<?php

declare(strict_types=1);

use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

(static function (string $extensionKey = 'dv_sso_auth'): void {
    ExtensionUtility::registerPlugin(
        'DvSsoAuth',
        'Login',
        'SSO Login',
        'loginIcon'
    );

    $settings = GeneralUtility::makeInstance(ExtensionSettingsFactory::class)
        ->createFromExtensionConfiguration($extensionKey);

    if (!$settings->enableFE) {
        return;
    }

    $pluginSignature = str_replace('_', '', $extensionKey) . '_login';

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature] = 'pi_flexform';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature] = 'select_key,pages,recursive';

    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignature,
        'FILE:EXT:dv_sso_auth/Configuration/FlexForms/Login.xml'
    );
})();
