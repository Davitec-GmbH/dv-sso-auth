.. include:: /Includes.rst.txt

============
Installation
============

.. contents:: On this page
   :local:
   :depth: 2

Requirements
============

- TYPO3 ``12.4 LTS``, ``13.4 LTS`` or ``14``
- PHP ``8.1`` or newer (see exact constraints in ``composer.json``)
- Working SSO/IdP setup (e.g. Shibboleth SP) providing server variables
  such as ``REMOTE_USER``

Install via Composer
====================

Run in your TYPO3 project root:

.. code-block:: bash

   composer require davitec/dv-sso-auth

For Composer-based installations the extension is activated automatically.

If required, verify activation in
:guilabel:`Admin Tools > Extension Manager`.

Configure extension settings
============================

Open :guilabel:`Admin Tools > Settings > Extension Configuration` and configure
``dv_sso_auth``.

Minimum useful setup:

1. Enable at least one login context:

   - ``enableBE`` for TYPO3 backend SSO
   - ``enableFE`` for TYPO3 frontend SSO

2. Set handler paths if your Shibboleth endpoints differ:

   - ``loginHandler`` (default: ``/Shibboleth.sso/Login``)
   - ``logoutHandler`` (default: ``/Shibboleth.sso/Logout``)

3. Verify IdP variable mapping:

   - ``remoteUser`` (default: ``REMOTE_USER``)
   - ``mail`` (default: ``mail``)
   - ``displayName`` (default: ``displayName``)
   - ``eduPersonAffiliation`` (default: ``affiliation``)

4. If FE user auto-import is enabled, configure ``storagePid`` to the
   SysFolder that should contain FE users and FE groups.

Include TypoScript
==================

The extension provides TypoScript in:

- ``EXT:dv_sso_auth/Configuration/TypoScript/constants.typoscript``
- ``EXT:dv_sso_auth/Configuration/TypoScript/setup.typoscript``

Import it in your site package TypoScript:

.. code-block:: typoscript

   @import 'EXT:dv_sso_auth/Configuration/TypoScript/constants.typoscript'
   @import 'EXT:dv_sso_auth/Configuration/TypoScript/setup.typoscript'

Frontend plugin setup
=====================

For frontend SSO, create a content element with plugin **SSO Login**
(``dvssoauth_login``).

If ``enableFE`` is active, the plugin provides a FlexForm field
``settings.redirectPage`` to define the page shown after successful login
when no ``redirect_url`` parameter is present.

Quick verification
==================

1. Frontend: open a page with the **SSO Login** plugin and trigger login.
2. Backend: open ``/typo3/login`` and verify the SSO button is available
   when ``enableBE`` is enabled.
3. Confirm that authenticated users are matched by username against the
   configured ``remoteUser`` server variable.
