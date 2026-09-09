# Exclusive authentication and account menu

Implemented 2026-09-05.

- Community and administrator authentication are mutually exclusive within one browser session. Signing in as either role clears the other role and records the active identity type. Legacy sessions containing both flags are normalized on the next request.
- The community account's chosen name appears beside the Report emergency action. Administrators receive an Administrator identity control in the same location.
- Logout and Admin sign out were removed from primary navigation. The identity control opens a compact menu containing the identity label and a single Sign out action.
- The menu works without JavaScript through native details/summary semantics. JavaScript adds outside-click and Escape dismissal with focus restoration.
- Long names truncate in the header but remain available in full inside the menu. The mobile header places the name and Report emergency action together on a second row.

Verification: all affected PHP and JavaScript syntax checks passed. The isolated auth integration test passed admin-to-community and community-to-admin replacement, menu-only sign out, Escape handling, desktop layout, mobile layout, and horizontal overflow. Its temporary database account was removed and test-generated rate-limit records were restored/removed. The existing full UI suite also passed all routes, navigation, 320px and mobile layouts, slideshow, reduced motion, location cascade, form restoration, and provider retry. Detector output contained only existing design-system documentation advisories.
