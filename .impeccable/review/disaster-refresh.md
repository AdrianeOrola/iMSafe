# Disaster redesign and new location API — 2026-09-05

## Direction and scope

The user explicitly rejected the plain light theme and requested the previous design's energy, stronger disaster relevance, and a different working location API. The shipped theme uses storm navy, cyan, safety orange, an original fictional rescue illustration, and pausable homepage motion. Report, track, account, and operations pages share the dark navigation/footer while working surfaces remain readable and still.

The core reporting copy and workflow remain. Inherited unverified “24/7”, “50+ LGUs”, and response-standing-by claims were replaced with clearly illustrative disaster-response content; the artwork is not a live incident feed.

## Review

In-thread review fallback: no independent reviewer agent was available. Batched desktop/mobile inspection followed by one correction/confirmation round. The mobile image caption was reduced so it no longer obscures the rescue scene. Both operations branches load the shared theme. No further visual iteration is needed for this scope.

The Impeccable detector ran once after the visual edits. It reported design-system palette/type/radius documentation advisories against the existing light-theme DESIGN.md; this is not a clean detector result. The root design notes and sidecar remain unchanged pending the user's answer to the refresh question.

## Verification

- 28 PHP files pass syntax checks; app.js and home.js pass JavaScript syntax checks.
- Public routes tested at 1440/390px; overflow checked at 320/720px.
- Navigation disclosure and Escape dismissal pass.
- Motion pause and prefers-reduced-motion checks pass. Animation is enabled only when the pause control has JavaScript support.
- Live Cavite → Bacoor → barangay and NCR → Manila → barangay cascades pass.
- Changed-parent stale-response protection, location retry, and rejected-report restoration pass.
- Isolated operations sign-in and synthetic queue layouts pass at 1440/1280/390/320px. No real incident or account was created.
- 23 isolated location checks pass: hierarchy, wrong provider labels, tampered hidden names, Manila sub-municipalities, malformed/empty/partial/duplicate data, outage, corrupt cache, stale cache, and bounded timeout.

## Provider implementation and limitations

The server now calls PSGC Cloud v2, configured with IMSAFE_LOCATION_API_URL (default https://psgc.cloud/api/v2). Old IMSAFE_LOCATION_PRIMARY_URL and IMSAFE_LOCATION_SECONDARY_URL settings are no longer used. Browser asset versions and location-cache namespaces were changed to avoid mixing the old nine-digit and new ten-digit formats. Existing stored incidents are not rewritten.

Province-specific city endpoints validate hierarchy. The adapter ignores unreliable free-text province labels, removes SubMun entries from city choices, explicitly handles NCR without a province, and concurrently assembles Manila barangays from its sub-municipalities. Fresh validated lists are cached for a day; stale lists are used on upstream failure. A cold outage with no saved list still fails safely and offers retry.

Live checks returned 23 Cavite municipalities/cities, 73 Bacoor barangays, 17 NCR municipalities/cities, and 897 Manila barangays. The provider's region endpoint currently returns 17 regions; its dataset is not fully current. A current authoritative snapshot remains necessary before claiming complete national coverage. Do not silently invent an updated hierarchy or advertise this as production-ready emergency infrastructure.

## Assets and design notes

assets/images/PROVENANCE.md records the original generated illustration and prompt. assets/atmosphere.css is the shared dark theme; assets/home.css and home.js own the homepage scene and its motion. DESIGN.md still describes the preceding light design pending confirmation to refresh it.

## Verdict

Implemented and tested at the requested redesign and tested-location-flow scope, with the provider coverage limitation disclosed. Not a full national dataset certification or a production-readiness certification.
