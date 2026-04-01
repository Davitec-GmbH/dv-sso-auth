.. include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

.. contents:: On this page
   :local:
   :depth: 2

Extension configuration (ext_conf_template)
===========================================

Main settings are configured in TYPO3 extension configuration for
``dv_sso_auth``.

.. t3-field-list-table::
   :header-rows: 1

   - :Setting: Setting
     :Type: Type
     :Default: Default
     :Description: Description

   - :Setting: ``enableBE``
     :Type: bool
     :Default: ``0``
     :Description:
        Enables SSO authentication integration for TYPO3 backend login.

   - :Setting: ``enableFE``
     :Type: bool
     :Default: ``0``
     :Description:
        Enables SSO authentication integration for TYPO3 frontend login.

   - :Setting: ``enableAutoImport``
     :Type: bool
     :Default: ``0``
     :Description:
        Automatically creates/updates FE users from IdP attributes when they
        do not exist or have changed.

   - :Setting: ``enableBackendAutoImport``
     :Type: bool
     :Default: ``0``
     :Description:
        Automatically creates/updates BE users from IdP attributes.

   - :Setting: ``backendAutoImportGroup``
     :Type: string
     :Default: ``BackendGroup``
     :Description:
        Required affiliation for BE auto-import. If empty, all affiliations
        are accepted.

   - :Setting: ``FE_fetchUserIfNoSession``
     :Type: bool
     :Default: ``0``
     :Description:
        Forces FE auth service execution even if no FE session exists.

   - :Setting: ``BE_fetchUserIfNoSession``
     :Type: bool
     :Default: ``1``
     :Description:
        Forces BE auth service execution even if no BE session exists.

   - :Setting: ``forceSSL``
     :Type: bool
     :Default: ``1``
     :Description:
        Forces generated login targets to ``https``.

   - :Setting: ``onlySsoBE``
     :Type: bool
     :Default: ``0``
     :Description:
        Disallows backend login without SSO context. See
        :ref:`onlyssobe-hook`.

   - :Setting: ``priority``
     :Type: int (0-100)
     :Default: ``90``
     :Description:
        TYPO3 auth service priority.

   - :Setting: ``storagePid``
     :Type: int
     :Default: ``0``
     :Description:
        Storage PID for FE users and FE groups created by auto-import.

   - :Setting: ``loginHandler``
     :Type: string
     :Default: ``/Shibboleth.sso/Login``
     :Description:
        SSO login endpoint path used for frontend and backend login links.

   - :Setting: ``logoutHandler``
     :Type: string
     :Default: ``/Shibboleth.sso/Logout``
     :Description:
        SSO logout endpoint path used for frontend logout redirect.

   - :Setting: ``remoteUser``
     :Type: string
     :Default: ``REMOTE_USER``
     :Description:
        Server variable containing the authenticated username.

   - :Setting: ``mail``
     :Type: string
     :Default: ``mail``
     :Description:
        Server variable containing the user email address.

   - :Setting: ``displayName``
     :Type: string
     :Default: ``displayName``
     :Description:
        Server variable containing display name / real name.

   - :Setting: ``eduPersonAffiliation``
     :Type: string
     :Default: ``affiliation``
     :Description:
        Server variable containing affiliation values for group mapping.

   - :Setting: ``typo3LoginTemplate``
     :Type: string
     :Default: ``EXT:dv_sso_auth/Resources/Private/Templates/BackendLogin/SsoLogin.html``
     :Description:
        Fluid template path for the TYPO3 backend login provider.

TypoScript
==========

The extension ships with TypoScript constants and setup for plugin view paths:

.. code-block:: typoscript

   plugin.tx_dvssoauth {
     view {
       templateRootPath = EXT:dv_sso_auth/Resources/Private/Templates/
       partialRootPath = EXT:dv_sso_auth/Resources/Private/Partials/
       layoutRootPath = EXT:dv_sso_auth/Resources/Private/Layouts/
     }
   }

If needed, override these paths in your site package.

Frontend plugin and FlexForm
============================

The plugin is registered as ``dvssoauth_login``.

Available FlexForm settings:

- ``settings.redirectPage``: page used after successful login if no explicit
  ``redirect_url`` was passed.
- ``settings.logoutRedirectPage``: currently defined in FlexForm but not used
  in controller logic.

Server variable resolution
==========================

Server variables are resolved with fallback handling:

- direct key lookup (example: ``REMOTE_USER``)
- prefixed lookup with ``REDIRECT_`` (example: ``REDIRECT_REMOTE_USER``)
- scan all scalar ``$_SERVER`` keys and strip the ``REDIRECT_`` prefix

This makes setups behind Apache rewrite/proxy layers more robust.

Affiliation parsing
===================

Affiliations are parsed from the configured server variable
(``eduPersonAffiliation``):

- split by ``;``
- trim whitespace
- strip domain suffixes after ``@`` (``member@example.org`` becomes ``member``)
- deduplicate values

For FE users, if no affiliation is available, the fallback group title
``member`` is used.

.. _onlyssobe-hook:

Hooks / extension points
========================

The auth service exposes extension points through
``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dv_sso_auth']``:

- ``getFEUserGroups``: post-process FE group UID list
- ``getBEUserGroups``: post-process BE group UID list
- ``onlySsoFunc``: custom behavior when ``onlySsoBE`` denies non-SSO login

Example registration:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dv_sso_auth']['getFEUserGroups'][]
       = \Vendor\Site\Auth\FrontendGroupProcessor::class;

   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dv_sso_auth']['getBEUserGroups'][]
       = \Vendor\Site\Auth\BackendGroupProcessor::class;

   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dv_sso_auth']['onlySsoFunc'][]
       = \Vendor\Site\Auth\OnlySsoProcessor::class;

Expected processor methods:

.. code-block:: php

   public function getFEUserGroups(array $groupUids): array {}
   public function getBEUserGroups(array $groupUids): array {}
   public function onlySsoFunc(?string $remoteUser): void {}
