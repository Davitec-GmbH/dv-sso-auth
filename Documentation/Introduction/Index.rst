.. include:: /Includes.rst.txt

============
Introduction
============

.. contents:: On this page
   :local:
   :depth: 2

What does it do?
================

**DV SSO Auth** integrates Shibboleth-based single sign-on (SSO) into TYPO3.
It provides a dedicated TYPO3 authentication service and optional user
provisioning for frontend and backend accounts.

Features
========

- SSO authentication service for TYPO3 frontend and backend login
- Dedicated backend login provider with an SSO button
- Frontend login plugin with SSO login and logout flow
- Optional auto-import and update of FE users from IdP attributes
- Optional auto-import and update of BE users from IdP attributes
- FE and BE group mapping based on affiliation attributes
- Frontend middleware to recover from stale FE session cookies during SSO
- Hook extension points for custom group assignment and `onlySsoBE` handling

How SSO requests are detected
=============================

The extension treats a request as SSO-related when one of these conditions
is true:

- ``AUTH_TYPE=shibboleth`` (case-insensitive)
- ``Shib_Session_ID`` exists
- ``REDIRECT_Shib_Session_ID`` exists

This logic is implemented in ``SsoRequestDetector``.

Compatibility
=============

According to ``composer.json`` the extension supports:

- TYPO3 ``^12.4 || ^13.4 || ^14.0``
- PHP ``^8.1 || ^8.2 || ^8.3 || ^8.4 || ^8.5``
