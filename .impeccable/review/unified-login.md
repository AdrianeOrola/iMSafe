# Unified community and administrator login

Implemented 2026-09-06.

- Removed the public Operations navigation item and the public footer link to the dashboard.
- Community and administrator access now share one email-and-password form at `account.php?mode=login`; there is no account-type selector.
- The server recognizes the configured administrator email and otherwise checks the community account repository. Successful administrator authentication redirects to the monitoring dashboard.
- Signed-out dashboard requests redirect to the unified login page. The old separate dashboard login form is no longer rendered.
- The homepage coordination link opens the same login page. Signed-in administrators can return to monitoring through their identity menu.
- Existing mutually exclusive roles, CSRF validation, session rotation, login throttling, chosen-name display, and menu-only sign out remain intact.

Verification: the unified authentication integration passed both credential types and sign-out behavior. The isolated dashboard test passed using administrator email and password and rendered the live and synthetic dashboard states at 1440, 1280, 390, and 320 pixels. The full public UI suite passed all routes, navigation, narrow layouts, slideshow, reduced motion, location cascade, restored form data, and provider retry. The single login form was reviewed at 1440 and 390 pixels with no horizontal overflow. Detector results contained only existing design-system documentation advisories.
