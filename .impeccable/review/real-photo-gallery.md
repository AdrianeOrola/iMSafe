# Real-photo homepage restoration

Implemented the user's request to bring fitting photos and motion back to the homepage. No AI raster was restored. Two real, locally served CC BY 2.0 photographs show Typhoon Haiyan aftermath in Tacloban and relief distribution in Dulag, Leyte. Archive context, photographer credits, source links, license links, and display modifications are documented visibly in the homepage's Photo credits disclosure and in assets/images/PHOTO-CREDITS.md.

The existing navy/orange identity, headline, reporting links, and sliding four-step strip remain. The tracking page remains photo-free, and the removed visible pause button and Community response / Disaster response illustration labels remain absent.

Motion purpose: explanatory homepage imagery linking hazard impact with relief. CSS opacity crossfade (800ms strong ease-out), 6.5-second dwell, and an 18-second linear gentle zoom. No library added. Manual photo selection stops autoplay; clicking the photo can toggle playback. Hover/focus, hidden tabs, and offscreen state suspend playback. Reduced motion uses stationary photographs with manual selection. With JavaScript unavailable, the first photograph and attribution remain visible.

Verification: public browser regression suite passed, including both locally loaded photos, automatic photo changes, manual selection, reduced motion, working credit disclosure, sliding-strip behavior, mobile navigation, photo-free tracking, Cavite/NCR selectors, provider retry, and rejected-form restoration. Layouts checked at 1440/390px and overflow at 320/720px. Desktop/mobile screenshots reviewed together. No new visual correction was needed. Main edited PHP and JavaScript pass syntax checks.

Detector: 31 color, 9 font-size, and 2 radius documentation advisories against the older design notes; no other finding categories. DESIGN.md and its sidecar were preserved rather than refreshed outside the current request.

Verdict: requested homepage photo/motion restoration implemented and browser-tested. Backend and provider behavior were not changed.
