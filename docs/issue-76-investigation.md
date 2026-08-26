# Issue 76: Joomla captive MFA 404 investigation

## Status

The `onUserLogin()` result theory is rejected. In Joomla 5.4.6,
`CMSApplication::login()` continues when the aggregated `onUserLogin` results do
not contain strict `false`. Consequently, an absent LoginGuard listener result
(`[]`) and an explicit successful result (`[true]`) have the same acceptance
outcome. LoginGuard must not be released or offered for runtime verification on
the strength of that callback.

`tests/joomla_login_event_aggregation.php` preserves this distinction as a
regression check. It models Joomla's strict-false gate and verifies both the
listener-absent and explicit-true cases, rather than invoking a plugin method and
assuming that its return value changes the captive redirect boundary.

## Isolation Candidate B: User post-login callback no-op

The site owner tested Isolation Candidate A from exact head
`c1a9e57f3091de7c8214caa9fbaa127267ec7215` on Joomla 5.4.6. A valid
administrator username and password still ended at `404 | NOT FOUND` rather
than Joomla's captive MFA page. Candidate A is therefore a failed isolation:
merely registering and executing the System - LoginGuard MFA captive subscribers
is not sufficient to trigger the observed result.

Candidate B completely reverts Candidate A. The installed and enabled System -
LoginGuard MFA plugin again registers all five normal v0.2.24 captive event
subscriptions and its handlers remain unchanged. The single diagnostic boundary
is now User - LoginGuard `onUserAfterLogin()`, whose body is a no-op. It performs
no payload inspection, database insert, audit or mail work, component access,
resolver/enforcement work, application access, or session/request mutation.
`onUserAuthorisation()` remains unchanged.

This candidate adds no redirect, does not access Joomla-owned MFA/session keys,
and does not restore pending-attempt correlation. Its sole runtime question is
whether valid administrator primary credentials now reach Joomla's captive MFA
page rather than the reported 404. This remains a v0.2.24 diagnostic build, not
a fix or release candidate, and must not be merged or released based on static
validation alone.

## v0.2.20 to v0.2.24 differential

### User authorisation listener

The `onUserAuthorisation($response = null, $options = [])` signature, its legacy
`AuthenticationResponse` path, its Event-object compatibility path, and its
allow-path `null` result are already present at the exact v0.2.20 commit
`9e94e92`. They are not a post-v0.2.20 differential. On the allow path the
listener does not add a result and does not mutate the authentication response.
The denial path intentionally changes the response status and, for Event-object
dispatch, adds the denied response to the event.

The plugin service provider is also unchanged between v0.2.20 and v0.2.24. It
constructs the CMS plugin with the dispatcher and does not manually register or
reorder listeners.

### Concrete session-affecting differential

v0.2.21 added work in `onUserAfterLogin()` which wrote
`plg_system_loginguardmfa.pending_attempt.<user id>` to Joomla's active session.
It also introduced the system `loginguardmfa` plugin and its captive-event
subscribers. These are concrete post-v0.2.20 changes at the lifecycle boundary:

1. the user plugin performed a database insert and then mutated the active
   session during `onUserAfterLogin`;
2. a newly installed and enabled system plugin subscribed to Joomla captive MFA
   events; and
3. that plugin later read and cleared the custom session key.

v0.2.24 removed all writes, reads, and clears of that custom session key. The
system plugin and its captive-event registration remain. Therefore the removed
session correlation is a source-backed historical candidate, but it cannot by
itself explain a 404 reproduced at the exact v0.2.24 head. The remaining
high-signal differential to isolate at runtime is registration of the system
plugin, followed by the post-login audit path's database/component/mail work.
All of this work is caught, but catching an exception does not undo application,
session, request, mail, or database side effects which happened before it.

## Required runtime matrix

No candidate is ready for site-owner testing as a fix. A Joomla 5.4.6 fixture
with mandatory administrator MFA should capture identity, session ID and keys,
request option/view, response status and redirect URI immediately before and
after the core MFA handler for each case:

1. exact v0.2.20 package;
2. exact v0.2.24 package;
3. v0.2.24 with only the `loginguardmfa` system plugin disabled;
4. v0.2.24 with the system plugin enabled but its subscribers reduced to no-op;
5. v0.2.24 with `onUserAfterLogin` reduced to no-op; and
6. v0.2.21 with only the custom pending-attempt session correlation removed.

The first case which changes the pre-handler state or captive redirect result is
the boundary for a narrower source investigation. Until that evidence exists,
Issue 76 remains an investigation and PR #77 must not be merged or released.
