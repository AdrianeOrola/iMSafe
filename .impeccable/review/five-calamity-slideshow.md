# Five-calamity homepage slideshow

Implemented 2026-09-05. Scope: replace the two relief photographs with five real public-domain historical hazard images, preserving the existing homepage layout and motion.

Sequence: Fire -> Earthquake -> Flood -> Typhoon -> Volcanic eruption -> Fire. Four-second interval, existing 800ms crossfade and subtle zoom. A matching hazard name appears within the existing image caption, not below the image. No visible credits, selector bars, or separate pause button. Historical dates and actual locations remain in alternative text; provenance is recorded in assets/images/PHOTO-CREDITS.md. Old image assets remain available but unused.

Animation skill guidance preserved reduced-motion behavior, manual keyboard navigation, offscreen/hidden-page suspension and failed-image skipping. Impeccable guidance kept the change scoped to the incumbent visual system and used one batched desktop/mobile review.

Validation:

- index.php PHP syntax check and both modified JavaScript syntax checks passed.
- tests/ui-smoke.cjs passed the complete automatic five-image loop, including advancement while hovered and return to the first slide.
- All five photographs loaded locally and were captured at 390px and 1440px; mobile crops and desktop homepage were visually reviewed.
- Reduced motion, direct pause/resume, sliding response strip, navigation, six routes, 320px/720px overflow checks, photo-free tracking, Cavite/NCR location cascade, form restoration and provider retry passed. No JavaScript errors.
- Impeccable detector returned only design-system documentation advisories: 27 color, 8 font-size, 2 radius. Existing design documentation differs from the current dark theme; no unrelated theme or documentation rewrite was made.

Images are illustrative archives, not live incident imagery. Three photographs depict U.S. hazards; Haiyan and Pinatubo depict Philippine events. No AI images were created or displayed.
