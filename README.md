# dv_sso_auth

`dv_sso_auth` is a TYPO3 extension for SSO-based authentication with
Shibboleth-focused defaults.

## Features

- SSO authentication service for TYPO3 frontend and backend login flows.
- Backend login provider with dedicated SSO button.
- Frontend login plugin with login/logout flow.
- Optional FE/BE user auto-import and update from IdP attributes.
- FE/BE group mapping from affiliation attributes.
- Middleware to recover from stale FE sessions during SSO login.

## Key configuration

Main settings are defined in `ext_conf_template.txt`:

- `enableBE` / `enableFE`
- `enableAutoImport` / `enableBackendAutoImport`
- `backendAutoImportGroup`
- `storagePid`
- `loginHandler` / `logoutHandler`
- `remoteUser`, `mail`, `displayName`, `eduPersonAffiliation`
- `onlySsoBE`, `forceSSL`

## Tests

Unit tests are located in `Tests/Unit` and cover core helper logic:

- `ExtensionSettingsFactory`
- `ServerVariableResolver`
- `SsoRequestDetector`
- `AffiliationParser`
- `ShibbolethTargetBuilder`

Run (once PHPUnit is available in your environment):

```bash
vendor/bin/phpunit -c packages/dv-sso-auth/phpunit.xml.dist
```

Run functional tests:

```bash
vendor/bin/phpunit \
  --bootstrap vendor/typo3/testing-framework/Resources/Core/Build/FunctionalTestsBootstrap.php \
  -c packages/dv-sso-auth/Tests/phpunit.xml \
  --testsuite Functional
```
