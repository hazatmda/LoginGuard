# Changelog

## 0.1.5-alpha

- Fixed the LoginGuard user plugin bootstrap so it no longer requests the unsupported `application` DI container resource during Joomla logout.
- Kept the existing login success and failed login audit logging flow unchanged while aligning application access with Joomla-native runtime access.

## 0.1.4-alpha

- Restored the Joomla 5 administrator component bootstrap and entry file so `com_loginguard` opens from the administrator menu.
- Explicitly registers the component element, services folder, dispatcher factory, and attempts view menu route.

## 0.1.3-alpha

- Corrected the administrator component bootstrap after the initial package/component release.

## 0.1.2-alpha

- Migrated LoginGuard packaging to `pkg_loginguard` while preserving the existing user plugin.
- Added the Joomla 5 administrator component scaffold for `com_loginguard`.
- Added an administrator login attempts list with search, status filtering, sorting, and pagination support.
- Updated package build and validation scripts for the package ZIP artifact.

## 0.1.0-alpha

- Initial Joomla 5 Login Guard skeleton
- Joomla user plugin structure
- Login success/failure event foundation
- SQL install/uninstall structure
- GitHub Actions package workflow foundation
