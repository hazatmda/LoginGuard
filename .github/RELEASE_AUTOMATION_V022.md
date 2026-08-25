Temporary implementation note for v0.2.22 release automation follow-up.

The Build LoginGuard Package workflow creates the canonical vVERSION GitHub Release automatically after validated pushes to main when that release does not already exist. The release job targets the validated main SHA and attaches only pkg_loginguard_vVERSION.zip.
