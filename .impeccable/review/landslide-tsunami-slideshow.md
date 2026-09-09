# Landslide and tsunami extension

Added 2026-09-05. The homepage now cycles through Fire, Earthquake, Flood, Typhoon, Volcanic eruption, Landslide and Tsunami every four seconds, then repeats. Two real public-domain archive photographs are stored locally; sources are recorded in assets/images/PHOTO-CREDITS.md. No visible credit section or selector bar was added.

Impeccable guidance preserved the incumbent layout and informed the landslide crop so the collapsed hillside stays visible on mobile. Animation guidance preserved the existing CSS opacity/transform treatment, reduced-motion alternative, manual interaction and offscreen suspension; no new animation dependency or timing change was needed.

Verification: PHP syntax and test JavaScript syntax passed. The complete seven-slide autoplay loop and existing UI regression suite passed, including reduced motion, photo-free tracking, narrow layouts, location cascade and provider retry. Both new images were visually inspected at 390px and 1440px in one batched review. The detector reported only the same existing documentation advisories (27 color, 8 font-size, 2 radius); unrelated design documentation was not rewritten.
