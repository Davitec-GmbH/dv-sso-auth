<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

(static function (string $extensionKey = 'dv_sso_auth'): void {
    ExtensionUtility::registerPlugin(
        'DvSsoAuth',
        'Login',
        'SSO Login',
        'loginIcon'
    );

    $pluginSignature = str_replace('_', '', $extensionKey) . '_login';
    $flexFormDataStructure = 'FILE:EXT:dv_sso_auth/Configuration/FlexForms/Login.xml';
    $typo3MajorVersion = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

    $GLOBALS['TCA']['tt_content']['types'][$pluginSignature]['showitem'] = '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,--palette--;;general,pi_flexform,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden,--palette--;;access';

    if ($typo3MajorVersion >= 14) {
        $GLOBALS['TCA']['tt_content']['types'][$pluginSignature]['columnsOverrides']['pi_flexform']['config']['ds'] = $flexFormDataStructure;
    } else {
        $GLOBALS['TCA']['tt_content']['types'][$pluginSignature]['columnsOverrides']['pi_flexform']['config']['ds']['*,' . $pluginSignature] = $flexFormDataStructure;
    }

    // TYPO3 <=13.3 expects DS mappings in the base pi_flexform ds map.
    if (is_array($GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'] ?? null)) {
        $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds']['*,' . $pluginSignature] = $flexFormDataStructure;
        // Keep old list_type records editable when existing installations have not migrated them yet.
        $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][$pluginSignature . ',list'] = $flexFormDataStructure;
    }
})();
