<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Typo3\Service;

use Davitec\DvSsoAuth\Configuration\ExtensionSettings;
use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use Davitec\DvSsoAuth\Domain\Authentication\SsoRequestDetector;
use Davitec\DvSsoAuth\Domain\Provisioning\AffiliationParser;
use Davitec\DvSsoAuth\Infrastructure\Server\ServerVariableResolver;
use TYPO3\CMS\Backend\Exception as BackendException;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SsoAuthenticationService extends AbstractAuthenticationService
{
    private const EXTENSION_KEY = 'dv_sso_auth';

    private ConnectionPool $connectionPool;

    private ServerVariableResolver $serverVariableResolver;

    private SsoRequestDetector $ssoRequestDetector;

    private AffiliationParser $affiliationParser;

    private ExtensionSettingsFactory $settingsFactory;

    private ExtensionSettings $settings;

    private ?string $remoteUser = null;

    public function __construct(
        ?ConnectionPool $connectionPool = null,
        ?ServerVariableResolver $serverVariableResolver = null,
        ?SsoRequestDetector $ssoRequestDetector = null,
        ?AffiliationParser $affiliationParser = null,
        ?ExtensionSettingsFactory $settingsFactory = null,
    ) {
        $this->connectionPool = $connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);
        $this->serverVariableResolver = $serverVariableResolver ?? GeneralUtility::makeInstance(ServerVariableResolver::class);
        $this->ssoRequestDetector = $ssoRequestDetector ?? GeneralUtility::makeInstance(SsoRequestDetector::class);
        $this->affiliationParser = $affiliationParser ?? GeneralUtility::makeInstance(AffiliationParser::class);
        $this->settingsFactory = $settingsFactory ?? GeneralUtility::makeInstance(ExtensionSettingsFactory::class);
        $this->settings = $this->settingsFactory->createFromArray([]);
    }

    public function init(): bool
    {
        $this->settings = $this->settingsFactory->createFromExtensionConfiguration(self::EXTENSION_KEY);
        $this->remoteUser = $this->serverVariableResolver->resolve($_SERVER, $this->settings->remoteUser);

        if (($this->remoteUser === null || $this->remoteUser === '') && $this->settings->remoteUser !== 'REMOTE_USER') {
            $this->remoteUser = $this->serverVariableResolver->resolve($_SERVER, 'REMOTE_USER');
        }

        return parent::init();
    }

    /**
     * @param string $mode
     * @param array<string, mixed> $loginData
     * @param array<string, mixed> $authInfo
     */
    public function initAuth($mode, $loginData, $authInfo, $pObj): void
    {
        if (Environment::isCli()) {
            parent::initAuth($mode, $loginData, $authInfo, $pObj);
            return;
        }

        if (!$this->settings->enableFE && $this->isLoginTypeFrontend()) {
            parent::initAuth($mode, $loginData, $authInfo, $pObj);
            return;
        }

        $this->login = $loginData;

        if (($this->remoteUser ?? '') !== '' && empty($this->login['uname'])) {
            $loginData['status'] = 'login';
        }

        parent::initAuth($mode, $loginData, $authInfo, $pObj);
    }

    public function getUser()
    {
        $user = false;

        if (($this->login['status'] ?? '') === 'login' && $this->isSsoLogin() && empty($this->login['uname'])) {
            $user = $this->fetchSsoUserRecord();

            if (!is_array($user) || $user === []) {
                $user = $this->handleMissingUser();
            } else {
                $this->handleExistingUser();
            }

            if ($this->isLoginTypeFrontend() || $this->isLoginTypeBackend()) {
                $user = $this->fetchSsoUserRecord();
            }
        }

        if (!Environment::isCli() && $this->isLoginTypeBackend() && $this->settings->onlySsoBE && empty($user)) {
            $this->denyBackendLoginWithoutSso();
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function authUser(array $user): int
    {
        if (Environment::isCli()) {
            return 100;
        }

        if ($this->isLoginTypeFrontend() && !empty($this->login['uname']) && !$this->isSsoLogin()) {
            return 100;
        }

        if ($this->isSsoLogin() && $user !== [] && $this->matchesRemoteUser($user)) {
            return $this->isDomainLockMatching($user) ? 200 : 0;
        }

        return 100;
    }

    private function handleMissingUser(): bool|array
    {
        if ($this->isLoginTypeFrontend() && $this->settings->enableAutoImport && $this->remoteUser !== null && $this->remoteUser !== '') {
            $this->importFrontendUser();
            return false;
        }

        if ($this->isLoginTypeBackend() && $this->settings->enableBackendAutoImport && $this->remoteUser !== null && $this->remoteUser !== '') {
            if ($this->importBackendUser()) {
                return false;
            }

            $this->writelog(
                255,
                3,
                3,
                2,
                "Login attempt from %s (%s), username '%s' found but not configured for backend access!",
                [$this->authInfo['REMOTE_ADDR'] ?? '', $this->authInfo['REMOTE_HOST'] ?? '', (string)$this->remoteUser]
            );

            return false;
        }

        $this->writelog(
            255,
            3,
            3,
            2,
            "Login attempt from %s (%s), username '%s' not found!",
            [$this->authInfo['REMOTE_ADDR'] ?? '', $this->authInfo['REMOTE_HOST'] ?? '', (string)$this->remoteUser]
        );

        return false;
    }

    private function handleExistingUser(): void
    {
        if ($this->isLoginTypeFrontend() && $this->settings->enableAutoImport) {
            $this->updateFrontendUser();
            return;
        }

        if ($this->isLoginTypeBackend() && $this->settings->enableBackendAutoImport) {
            $this->updateBackendUser();
        }
    }

    private function fetchSsoUserRecord(): bool|array
    {
        if (!$this->isLoginTypeFrontend()) {
            return $this->fetchUserRecord((string)$this->remoteUser);
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_users');

        $recordData = $queryBuilder
            ->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter((string)$this->remoteUser)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->settings->storagePid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $recordData ?: false;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function matchesRemoteUser(array $user): bool
    {
        $column = (string)($this->authInfo['db_user']['username_column'] ?? 'username');

        return (string)$this->remoteUser !== '' && (string)($user[$column] ?? '') === (string)$this->remoteUser;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isDomainLockMatching(array $user): bool
    {
        if (!isset($user['lockToDomain']) || $user['lockToDomain'] === '' || $user['lockToDomain'] === null) {
            return true;
        }

        $requestHost = (string)($this->authInfo['HTTP_HOST'] ?? '');
        if ((string)$user['lockToDomain'] === $requestHost) {
            return true;
        }

        if ($this->writeAttemptLog) {
            $this->writelog(
                255,
                3,
                3,
                1,
                "Login attempt from %s (%s), username '%s', locked domain '%s' did not match '%s'!",
                [
                    $this->authInfo['REMOTE_ADDR'] ?? '',
                    $this->authInfo['REMOTE_HOST'] ?? '',
                    (string)$user[$this->authInfo['db_user']['username_column'] ?? 'username'],
                    (string)$user['lockToDomain'],
                    $requestHost,
                ]
            );
        }

        return false;
    }

    private function importFrontendUser(): void
    {
        $frontendUserData = [
            'tstamp' => time(),
            'pid' => $this->settings->storagePid,
            'username' => (string)$this->remoteUser,
            'password' => $this->getRandomPassword(),
            'email' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->mail) ?? '',
            'name' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->displayName) ?? '',
            'usergroup' => $this->getFEUserGroups(),
        ];

        $query = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $query->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $existingUser = $query
            ->select('uid')
            ->from('fe_users')
            ->where(
                $query->expr()->eq('username', $query->createNamedParameter((string)$this->remoteUser)),
                $query->expr()->eq('pid', $query->createNamedParameter($this->settings->storagePid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (is_array($existingUser) && $existingUser !== []) {
            return;
        }

        $deletedUserQuery = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $deletedUserQuery->getRestrictions()->removeAll();

        $deletedUser = $deletedUserQuery
            ->select('uid')
            ->from('fe_users')
            ->where(
                $deletedUserQuery->expr()->eq('username', $deletedUserQuery->createNamedParameter((string)$this->remoteUser)),
                $deletedUserQuery->expr()->eq('pid', $deletedUserQuery->createNamedParameter($this->settings->storagePid, Connection::PARAM_INT)),
                $deletedUserQuery->expr()->eq('deleted', $deletedUserQuery->createNamedParameter(1, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (is_array($deletedUser) && $deletedUser !== []) {
            $this->writelog(255, 3, 3, 2, 'Restoring FE user %s.', [(string)$this->remoteUser]);

            $this->getDatabaseConnectionForUsers()->update(
                (string)$this->authInfo['db_user']['table'],
                array_merge($frontendUserData, ['deleted' => 0]),
                ['uid' => (int)$deletedUser['uid']]
            );

            return;
        }

        $this->writelog(255, 3, 3, 2, 'Importing FE user %s.', [(string)$this->remoteUser]);

        $this->getDatabaseConnectionForUsers()->insert(
            (string)$this->authInfo['db_user']['table'],
            array_merge(['crdate' => time()], $frontendUserData)
        );
    }

    private function importBackendUser(): bool
    {
        $this->writelog(255, 3, 3, 2, 'Importing BE user %s.', [(string)$this->remoteUser]);

        $affiliations = $this->affiliationParser->parse(
            $this->serverVariableResolver->resolve($_SERVER, $this->settings->eduPersonAffiliation)
        );

        $this->importBackendUserGroups($affiliations);

        if (
            $this->settings->backendAutoImportGroup !== ''
            && !in_array($this->settings->backendAutoImportGroup, $affiliations, true)
        ) {
            return false;
        }

        $this->getDatabaseConnectionForUsers()->insert(
            (string)$this->authInfo['db_user']['table'],
            [
                'crdate' => time(),
                'tstamp' => time(),
                'pid' => 0,
                'username' => (string)$this->remoteUser,
                'password' => $this->getRandomPassword(),
                'email' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->mail),
                'realName' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->displayName),
                'usergroup' => $this->getBEUserGroups(),
            ]
        );

        return true;
    }

    /**
     * @param list<string> $affiliations
     */
    private function importBackendUserGroups(array $affiliations): void
    {
        if ($affiliations === []) {
            return;
        }

        $query = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $query->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $existingGroups = $query
            ->select('title')
            ->from('be_groups')
            ->where(
                $query->expr()->in('title', $query->createNamedParameter($affiliations, Connection::PARAM_STR_ARRAY))
            )
            ->executeQuery()
            ->fetchFirstColumn();

        $groupsToCreate = array_diff($affiliations, array_map('strval', $existingGroups));

        foreach ($groupsToCreate as $groupTitle) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
            $queryBuilder
                ->insert('be_groups')
                ->values([
                    'title' => $groupTitle,
                    'tstamp' => time(),
                    'crdate' => time(),
                ])
                ->executeStatement();
        }
    }

    private function updateFrontendUser(): void
    {
        $this->writelog(255, 3, 3, 2, 'Updating FE user %s.', [(string)$this->remoteUser]);

        $this->getDatabaseConnectionForUsers()->update(
            (string)$this->authInfo['db_user']['table'],
            [
                'tstamp' => time(),
                'username' => (string)$this->remoteUser,
                'password' => $this->getRandomPassword(),
                'email' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->mail),
                'name' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->displayName),
                'usergroup' => $this->getFEUserGroups(),
            ],
            [
                'username' => (string)$this->remoteUser,
                'pid' => $this->settings->storagePid,
            ]
        );
    }

    private function updateBackendUser(): void
    {
        $affiliations = $this->affiliationParser->parse(
            $this->serverVariableResolver->resolve($_SERVER, $this->settings->eduPersonAffiliation)
        );

        $this->importBackendUserGroups($affiliations);

        $this->writelog(255, 3, 3, 2, 'Updating BE user %s.', [(string)$this->remoteUser]);

        $this->getDatabaseConnectionForUsers()->update(
            (string)$this->authInfo['db_user']['table'],
            [
                'tstamp' => time(),
                'username' => (string)$this->remoteUser,
                'password' => $this->getRandomPassword(),
                'email' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->mail),
                'realName' => $this->serverVariableResolver->resolve($_SERVER, $this->settings->displayName),
                'usergroup' => $this->getBEUserGroups(),
            ],
            [
                'username' => (string)$this->remoteUser,
            ]
        );
    }

    private function getFEUserGroups(): string
    {
        $affiliations = $this->affiliationParser->parse(
            $this->serverVariableResolver->resolve($_SERVER, $this->settings->eduPersonAffiliation)
        );

        if ($affiliations === []) {
            $affiliations = ['member'];
        }

        $frontendGroupUids = [];
        foreach ($affiliations as $affiliation) {
            $frontendGroupUids[] = $this->getOrCreateFrontendUserGroupByTitle($affiliation);
        }

        if (
            isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getFEUserGroups'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getFEUserGroups'])
        ) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getFEUserGroups'] as $classReference) {
                $processor = GeneralUtility::makeInstance($classReference);
                $frontendGroupUids = $processor->getFEUserGroups($frontendGroupUids);
            }
        }

        return implode(',', $frontendGroupUids);
    }

    private function getBEUserGroups(): string
    {
        $affiliations = $this->affiliationParser->parse(
            $this->serverVariableResolver->resolve($_SERVER, $this->settings->eduPersonAffiliation)
        );

        $backendGroupUids = [];

        foreach ($affiliations as $affiliation) {
            $uid = $this->getBackendUserGroupByTitle($affiliation);
            if ($uid !== null) {
                $backendGroupUids[] = $uid;
            }
        }

        if (
            isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getBEUserGroups'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getBEUserGroups'])
        ) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['getBEUserGroups'] as $classReference) {
                $processor = GeneralUtility::makeInstance($classReference);
                $backendGroupUids = $processor->getBEUserGroups($backendGroupUids);
            }
        }

        return implode(',', $backendGroupUids);
    }

    private function isSsoLogin(): bool
    {
        return $this->ssoRequestDetector->isSsoRequest($_SERVER) && (string)$this->remoteUser !== '';
    }

    private function getRandomPassword(): string
    {
        $randomPassword = GeneralUtility::makeInstance(Random::class)->generateRandomBytes(32);
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');

        return $hashInstance->getHashedPassword($randomPassword);
    }

    private function isLoginTypeFrontend(): bool
    {
        return ($this->authInfo['loginType'] ?? '') === 'FE';
    }

    private function isLoginTypeBackend(): bool
    {
        return ($this->authInfo['loginType'] ?? '') === 'BE';
    }

    private function getDatabaseConnectionForUsers(): Connection
    {
        return $this->connectionPool->getConnectionForTable((string)$this->authInfo['db_user']['table']);
    }

    private function getOrCreateFrontendUserGroupByTitle(string $title): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_groups');

        $recordData = $queryBuilder
            ->select('uid')
            ->from('fe_groups')
            ->where(
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->settings->storagePid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (is_array($recordData) && isset($recordData['uid'])) {
            return (int)$recordData['uid'];
        }

        $connection = $this->connectionPool->getConnectionForTable('fe_groups');
        $connection->insert(
            'fe_groups',
            [
                'pid' => $this->settings->storagePid,
                'title' => $title,
            ]
        );

        return (int)$connection->lastInsertId('fe_groups');
    }

    private function getBackendUserGroupByTitle(string $title): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');

        $recordData = $queryBuilder
            ->select('uid')
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($recordData) || !isset($recordData['uid'])) {
            return null;
        }

        return (int)$recordData['uid'];
    }

    private function denyBackendLoginWithoutSso(): void
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['onlySsoFunc'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][self::EXTENSION_KEY]['onlySsoFunc'] as $classReference) {
                $processor = GeneralUtility::makeInstance($classReference);
                $processor->onlySsoFunc($this->remoteUser);
            }
        } else {
            throw new BackendException('Login without SSO is not permitted.', 1738801183);
        }

        foreach (array_keys($_COOKIE) as $cookieKey) {
            unset($_COOKIE[$cookieKey]);
        }

        exit;
    }
}
