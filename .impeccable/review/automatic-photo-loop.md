# Automatic photo alternation without the credit/control row

Implemented the user's request: two photos alternate every four seconds, including while hovered. Removed the visible photo-credit disclosure, archive-credit link, selector bars, and their CSS/JavaScript handlers. The moving response-journey strip and photo-free tracking page remain unchanged.

To remove visible attribution without dropping license obligations, switched the rendered images to two verified public-domain U.S. military archive photographs: Haiyan evacuation in Tacloban and loading water-purification supplies for Philippine typhoon relief. Source and author records remain in assets/images/PHOTO-CREDITS.md. Previously displayed CC BY images remain unused with their source records intact.

Animation: existing opacity crossfade and gentle zoom; four-second dwell. Explicit click/keyboard stop remains available on the photo itself, without a separate control bar. Offscreen/hidden-tab suspension and reduced-motion support remain. Reduced-motion users can select the photo itself to advance without autoplay. A failed image is skipped rather than replacing a loaded image with a broken one.

Verification: browser regression suite passed, including image 1 → 2 → 1, continued alternation while hovered, click stop/resume, reduced-motion manual advancement, absence of credit/bar elements, desktop/mobile layouts, navigation, Cavite/NCR location selection, provider retry, and rejected-report restoration. Main edited PHP and JavaScript syntax checks passed. Desktop/mobile screenshots reviewed together; no visual correction required.

Detector result: 26 palette, 7 type-size and 2 radius documentation advisories against the prior design notes; no other categories. The existing design notes were not refreshed outside the requested scope.

Verdict: implemented and verified. Backend behavior unchanged.
