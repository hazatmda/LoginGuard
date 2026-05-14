# Changelog

## 0.1.8-alpha

- Rebuilt the administrator attempts ListView template around Joomla native SearchTools layout rendering.
- Aligned the attempts ListModel, filter XML, active filter state, sorting, where filtering, and pagination lifecycle with Joomla administrator MVC conventions.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.1.8-alpha.zip`.

## 0.1.7-alpha

- Fixed the Joomla plugin installer SQL lifecycle so fresh package installs create `#__loginguard_attempts` from `install.mysql.utf8.sql`.
- Added package update schema registration and a data-preserving installer script to reconcile alpha schemas during upgrades.
- Kept uninstall cleanup deterministic with `uninstall.mysql.utf8.sql` and installer lifecycle cleanup.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.1.7-alpha.zip`.

## 0.1.6-alpha

- Rebuilt LoginGuard login success/failure event handling with defensive Joomla payload normalization to support array, object, and User payloads.
- Added safe logout event handlers so LoginGuard never interrupts Joomla logout.
- Expanded the audit schema and administrator list foundation for ID, IP Address, Name, Username, Status, Datetime, Country, Browser, Operating System, and Where.
- Added a Joomla administrator HtmlView and SearchTools filter form for the audit table while preserving package/component/plugin architecture.
- Kept plaintext passwords out of all stored audit records.

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
