# Changelog

## 0.2.20
- Preserve raw submitted username telemetry and stop replacing empty or missing usernames with `unknown`.
- Store empty or missing usernames as `NULL` while preserving literal usernames such as `unknown`.
- Render nullable usernames as `NULL (empty)` in administrator UI and mail alerts while keeping CSV exports raw.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.20.zip`.

## 0.2.19

- Fixed LoginGuard timestamp rendering so emails, Login Information, dashboard, Blocked IP views, and CSV exports share one UTC-storage to Joomla-configured-timezone display path.
- Hardened Login Information column visibility persistence with the stable `loginguard.logininfo.columns` localStorage key so hidden columns survive refreshes and navigation.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.19.zip`.

## 0.2.18

- Modernized the Blocked IPs operational workspace with telemetry hierarchy, modal add/edit workflows, responsive cards/table behavior, clearer badge hierarchy, and conditional temporary expiration editing.
- Normalized LoginGuard administrator, export, and structured email timestamps through Joomla configured timezone formatting.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.18.zip`.

## 0.2.17

- Moved Joomla updater authority from the bootstrap package lifecycle to the `com_loginguard` component lifecycle.
- Published update-server metadata from the component manifest while keeping `pkg_loginguard` as the installer package for child extensions.
- Converted the update stream to component metadata for `com_loginguard` while retaining the package ZIP download artifact `pkg_loginguard_v0.2.17.zip`.
- Preserved package child-extension synchronization, update-site repair, and installer/update compatibility for fresh installs and upgrades.

## 0.2.16

- Added structured SOC-style HTML email rendering that maps template variables to telemetry rows without dumping raw template bodies.
- Preserved customizable subjects, labels, intro text, footer text, and plain-text fallback templates.
- Added automatic local GeoIP capability detection with graceful fallback and legacy map compatibility.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.16.zip`.

## 0.2.15

- Added configurable failed-login alert throttling with disabled-by-default behavior, a threshold of 10, and a 15-minute throttle window.
- Repaired failed-login alert suppression so every failed login can send when throttling is disabled and only threshold-exceeding alerts suppress when enabled.
- Hardened package update-site repair by refreshing the LoginGuard update site, rebinding package update-site mappings, and removing stale mappings during package lifecycle events.
- Moved release metadata to stable version `0.2.15` and synchronized package naming for `pkg_loginguard_v0.2.15.zip`.

## 0.2.14-alpha

- Refined LoginGuard audit emails with uppercase subjects, professional status/reason labels, HTML layouts with severity color accents, plain-text fallbacks, normalized labels, and the Login Guard MDA generated footer.
- Centered dashboard KPI card labels and values while removing manual Compact Dashboard Mode/Comfortable Mode density controls and related preference handling.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.14-alpha.zip`.

## 0.2.13-alpha

- Reworked dashboard KPI cards into a responsive seven-card strip that stacks cleanly on small screens, balances on medium screens, and expands to one horizontal row on large screens.
- Added the per-administrator All dashboard timeframe option and preserved Today as the default timeframe.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.13-alpha.zip`.

## 0.2.12-alpha

- Refined the administrator dashboard telemetry layout with a top KPI strip, per-administrator timeframe selector, compact operational health chips, and reduced dashboard spacing.
- Removed the Failed Login Trends widget while preserving SearchTools, CSV export, ACL, cleanup scheduler, retention automation, update detection, and installer/update lifecycle behavior.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.12-alpha.zip`.

## 0.2.11-alpha

- Redesigned the Dashboard into a compact SOC-style operational layout with denser telemetry strips, compressed metric cards, and reduced vertical whitespace.
- Split Quick Actions into operational status chips and compact administrator actions while moving high-priority attack trend, origin, country, and IP telemetry higher on the page.
- Added a persisted per-administrator Compact Dashboard Mode preference and synchronized release metadata/package naming for `pkg_loginguard_v0.2.11-alpha.zip`.

## 0.2.10-alpha

- Refined dashboard hierarchy with balanced operational overview, summary telemetry, and enriched Quick Actions scheduler/GeoIP context.
- Redesigned Blocked IPs operations around telemetry-first summary cards, compact Search Tools alignment, a collapsed add/edit workflow, status badges, expiration countdowns, and improved empty-state guidance.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.10-alpha.zip`.

## 0.2.9-alpha

- Modernized the Dashboard into an operational security overview with protection status, enforcement/scheduler health, cleanup visibility, quick actions, attack trends, top attacking IPs, backend/frontend summaries, and country telemetry.
- Added Joomla user-state compatible Login Information column visibility preferences while preserving SearchTools and full CSV export compatibility.
- Removed the Tools page and submenu while preserving export functionality inside Login Information.
- Enhanced the About page with owner, organization, repository, compatibility, release-channel, support, and operational guidance.
- Improved blocked-IP and empty-state UX with clearer operational status and expiration messaging.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.9-alpha.zip`.

## 0.2.8-alpha

- Added automatic retention cleanup configuration for login attempts, stale blocked IP records, batch size, and execution logging.
- Added a Joomla Scheduler task plugin that runs LoginGuard cleanup through bounded batch deletion instead of unrestricted large-table deletes.
- Added `CleanupService` for pruning old login attempts, expired temporary blocks, and disabled blocked-IP rows while recording cleanup metrics.
- Added cleanup run storage and dashboard retention metrics for total attempts, total blocked IPs, last cleanup execution, deleted-row counts, batches, and active retention policy visibility.
- Preserved SearchTools, CSV export, ACL, GeoIP telemetry, enforcement architecture, install/update lifecycle, update detection, and package build behavior.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.8-alpha.zip`.

## 0.2.6-alpha

- Stabilized Joomla package update metadata, update-server integration, direct release ZIP naming, and installer/update lifecycle checks for `pkg_loginguard_v0.2.6-alpha.zip`.
- Expanded GeoIP enrichment from country-only telemetry to country, country code, region, city, ISP, and ASN fields while preserving offline deterministic mapping.
- Added City, ISP, and ASN to the Login Information table and CSV export while preserving enforcement, whitelist, blocked IP management, Dashboard, SearchTools, ACL, and Joomla MVC behavior.
- Added active IP block enforcement, whitelist bypasses, temporary automatic threshold-based blocks, cooldown duration, and frontend/backend enforcement toggles.
- Added blocked IP telemetry on the dashboard, blocked login audit records, GeoIP country-map enrichment, expanded alert variables, and blocked IP mail alerts.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.6-alpha.zip`.

## 0.2.4-alpha

- Registered LoginGuard Dashboard, Login Information, Configuration, Tools, and About as Joomla-native administrator submenu items under Components > Login Guard.
- Removed the duplicate component-internal sidebar layout so navigation is provided by the administrator Components menu.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.2.4-alpha.zip`.

## 0.2.3-alpha

- Split dashboard telemetry into Frontend Success, Backend Success, Frontend Failed, and Backend Failed cards.
- Routed CSV export through the Joomla toolbar form task so SearchTools filters, selected rows, ACL, UTF-8 encoding, and download headers are preserved.
- Removed non-functional lockout duration and failed attempt threshold configuration fields until a real enforcement engine exists.
- Stabilized the administrator left sidebar with Joomla-native Sidebar rendering and synchronized release artifacts for `pkg_loginguard_v0.2.3-alpha.zip`.

## 0.2.2-alpha

- Fixed administrator sidebar layout wrappers so submenu content is placed beside the main panel consistently.
- Reduced dashboard origin metrics to frontend and backend counts only.
- Added configurable Joomla mail audit alerts for successful and failed login events with recipient, subject, body, template-variable, and failed-login IP throttling controls.
- Added Joomla update server metadata and synchronized release package naming for `pkg_loginguard_v0.2.2-alpha.zip`.

## 0.2.1-alpha

- Added a lightweight Joomla-native dashboard telemetry overview with success and failed login count cards.
- Added frontend/backend/api/cli summary metrics, recent activity, top failure reasons, and top failed IP panels while preserving Login Information as the full audit table.
- Kept dashboard telemetry behind existing core.manage and loginguard.view ACL checks, with existing toolbar permissions preserved.
- Synchronized package metadata and release artifacts for `pkg_loginguard_v0.2.1-alpha.zip`.

## 0.2.0-alpha

- Added Joomla administrator submenu architecture for Dashboard, Login Information, Configuration, Tools, and About.
- Moved the audit list under Login Guard → Login Information while preserving SearchTools sorting, filtering, and pagination.
- Added authentication telemetry enums for SUCCESS_LOGIN/FAILED_LOGIN, failure reasons, and frontend/backend/api/cli origin detection without password logging.
- Added Joomla-native ACL access.xml permissions, sensitive audit view checks, toolbar permission enforcement, delete/export permissions, and component com_config foundation.
- Synchronized package metadata and release artifacts for `pkg_loginguard_v0.2.0-alpha.zip`.

## 0.1.9-alpha

- Added a centralized proxy-aware IP resolver for Cloudflare, forwarded proxy headers, real IP headers, and localhost fallback support.
- Updated login audit logging to resolve client IP addresses through the reusable resolver instead of reading `REMOTE_ADDR` directly.
- Synchronized release metadata and package naming for `pkg_loginguard_v0.1.9-alpha.zip`.

## 0.1.8-alpha

- Replaced the administrator SearchTools helper call with Joomla LayoutHelper rendering to avoid `searchtools::default` runtime layout errors.
- Aligned the attempts ListModel, filter XML, SearchTools sorting, and Where filtering with Joomla administrator ListView conventions.
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
