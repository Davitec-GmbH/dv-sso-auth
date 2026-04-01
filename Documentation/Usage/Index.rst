.. include:: /Includes.rst.txt

=====
Usage
=====

.. contents:: On this page
   :local:
   :depth: 2

Frontend login flow
===================

Use the **SSO Login** plugin (``dvssoauth_login``) on a frontend page.

Default behavior:

1. Anonymous users see an **Intranet login** link.
2. The link points to ``loginHandler`` with an encoded ``target`` URL.
3. The target URL is normalized to contain ``logintype=login`` and ``pid``
   (including signed PID if TYPO3 login signing is active).
4. After successful IdP authentication, TYPO3 authenticates the user by the
   configured ``remoteUser`` variable.
5. Logged-in users see a logout action that redirects to ``logoutHandler``
   with ``return=<site-root-url>``.

Redirect behavior after frontend login
======================================

After login, ``FrontendLoginController::loginSuccessAction()`` redirects in
this order:

1. ``redirect_url`` request parameter (if present)
2. FlexForm setting ``settings.redirectPage`` (if configured)
3. Otherwise, the plugin renders the logout view directly

Backend login flow
==================

When ``enableBE`` is enabled:

- A dedicated backend login provider is registered.
- TYPO3 login page (``/typo3/login``) shows an SSO login button.
- The login button points to ``loginHandler`` with target
  ``/typo3/?login_status=login``.
- On backend logout, the extension removes the first cookie whose name starts
  with ``_shibsession_``.

Restrict backend access to SSO only
===================================

Set ``onlySsoBE = 1`` to deny backend login attempts without valid SSO
context.

Behavior on denied login:

- If ``EXTCONF['dv_sso_auth']['onlySsoFunc']`` handlers are registered, they
  are executed.
- Otherwise, the service throws a backend exception
  (``Login without SSO is not permitted.``).

Auto-import and updates
=======================

Frontend users
--------------

With ``enableAutoImport = 1``:

- Missing FE users are created in ``storagePid``.
- Existing FE users are updated on login.
- ``email``, ``name`` and ``usergroup`` are synchronized from IdP attributes.
- FE groups are auto-created in ``storagePid`` by affiliation title if missing.

Backend users
-------------

With ``enableBackendAutoImport = 1``:

- Missing BE users are created with ``pid = 0``.
- Existing BE users are updated on login.
- BE groups are created from affiliation titles when missing.
- User-to-group assignment is derived from parsed affiliations.
- If ``backendAutoImportGroup`` is set, user creation only happens when this
  affiliation is present.

Recover from stale FE session cookies
=====================================

The middleware ``ResetBrokenFrontendSessionMiddleware`` handles a common edge
case:

- A request has an FE session cookie and is SSO-related.
- TYPO3 responds with ``403`` because session/auth state is stale.

Then the middleware:

1. Expires the FE cookie.
2. Redirects once to the same URL with ``ssoSessionRetry=1``.
3. If retry still returns ``403``, the response is returned as-is (no loop).

Typical setup example
=====================

.. code-block:: none

   Root
   +-- Login
   |   Content: SSO Login plugin
   +-- Protected area
   +-- Logged-in landing page (optional, used as redirectPage)
   +-- Storage (SysFolder, not in menu)
       Stores: FE users and FE groups for auto-import

Configuration summary for this setup:

- ``enableFE = 1``
- ``enableAutoImport = 1`` (optional)
- ``storagePid = <uid-of-storage-folder>``
- FlexForm ``settings.redirectPage = <uid-of-landing-page>`` (optional)
