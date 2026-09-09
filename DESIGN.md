---
name: iMSafe v2.0 Emergency Field Guide
description: Readable community reporting with a disaster-response visual identity.
colors:
  ink: "#152b38"
  muted: "#52636d"
  paper: "#f3f5f4"
  surface: "#ffffff"
  line: "#d5dddc"
  navy: "#112d38"
  teal: "#086960"
  teal-soft: "#e5f2ed"
  signal: "#f6a43b"
  green: "#176444"
  orange: "#865000"
  red: "#a72c39"
typography:
  display:
    fontFamily: "Barlow Condensed, Arial Narrow, sans-serif"
    fontSize: "clamp(2.75rem, 4.6vw, 4.5rem)"
    fontWeight: 600
    lineHeight: 1.08
    letterSpacing: "-0.015em"
  body:
    fontFamily: "Segoe UI, system-ui, -apple-system, sans-serif"
    fontSize: "17px"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "Segoe UI, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 600
rounded:
  control: "7px"
  panel: "14px"
spacing:
  small: "16px"
  medium: "24px"
  large: "32px"
components:
  button-primary:
    backgroundColor: "{colors.teal}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "13px 22px"
    height: "50px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "11px 12px"
    height: "50px"
---

# Design System: iMSafe v2.0

## Overview

**Creative North Star: "Emergency field guide"**

The user delegated a disaster-related redesign while preserving content. Condensed signage-like headings give identity; plain reading text and native controls keep the reporting task familiar.

Key characteristics: readable controls, strong task hierarchy, restrained alert colors, shared navigation, and quiet form surfaces.

## Colors

Teal identifies normal actions. Signal orange marks the report entry point. Navy anchors the homepage, form heading, dashboard metrics, and footer. Cool paper separates white task surfaces. Green, Orange, and Red always have written severity labels.

The operations dashboard extends the semantic palette with blue for received, amber for verified, violet for dispatched, and green for resolved. These colors are never the only status cue: every chart, badge, and metric includes a written label and value. Red is reserved for active critical reports.

## Typography

Self-hosted Barlow Condensed SemiBold is the heading voice. Segoe UI/system sans is the reading and control face. Body text is 17px (16px on small screens), form controls and labels 16px, metadata generally 14–15px. The homepage display reaches 96px; ordinary headings use the smaller responsive ramp.

**The Reading Rule.** Never shrink input text to fit a column. Reflow the layout.

## Layout

Content widths range from 600px for account forms to 1440px for operations and the homepage. Report and tracking layouts collapse to one column at 960px. Navigation becomes a disclosure at 1120px. Small-screen outer gutters are 16–20px; desktop gutters are 24–32px.

The operations queue uses a contained horizontal scroll with a focusable labeled region and a small-screen hint. Never let a wide table widen the whole document.

The operations dashboard is decision-first: overview metrics, severity, seven-day activity, response pipeline, affected barangays, and hazard mix appear before the incident queue. At phone widths, affected-area rows and incident records become labeled stacked records rather than clipped tables. Operational forms remain collapsed until requested and stay attached to their report when expanded.

Announcements is part of the shared public navigation for guests, community members, and administrators. Only Dashboard is restricted to administrators. The dashboard uses a light working ground so its dark title and supporting copy remain readable, while the navy overview band preserves the emergency-response identity.

Announcements is a separate source-monitoring workspace. Connection status appears before feed content, weather and international signals occupy distinct columns on desktop, and the official-source directory closes the page. All regions stack in reading order on phones.

## Elevation & Depth

Flat surfaces use tonal separation and one-pixel borders, not shadows. The sticky white header and navy footer frame all routes.

## Shapes

Controls use modest rounded corners. Task panels use the larger panel radius. Native radio buttons and checkboxes remain visible.

Dense dashboard panels may use 12px corners and compact status labels may use 6px corners. Data tracks use smaller 4–5px corners because they are chart marks, not containers.

## Components

Shared PHP header/footer partials own site navigation. The mobile menu exposes its expanded state, closes on Escape and outside click, and leaves links available without JavaScript. The report action remains visible outside the menu.

Buttons use a short color transition, visible focus, and distinct disabled states. Inputs are at least 50px high. Error messages identify recovery; location choices announce loading and cache status. Reduced-motion preferences disable transitions.

Dashboard interface text uses the existing system sans at 12–15px for chart metadata and table labels, with 16px retained for controls. Barlow Condensed remains limited to dashboard titles and large metric values. External PAGASA and GDACS states must distinguish a successful zero-result response from an unavailable provider.

Source information uses three written states: Source reached, Cached copy, and Unavailable. Every reproduced item names its issuing agency and source timestamp. A five-minute page check may refresh the feed, but the interface says “latest available” rather than claiming real-time certainty. PAGASA, PHIVOLCS, NDRRMC, and GDACS links must point directly to their official domains.

## Do's and Don'ts

- Do preserve text, routes, reporting behavior, and severity labels.
- Do keep critical controls at readable sizes on phones.
- Do distinguish provider freshness from a claim of real-time emergency coverage.
- Don't reintroduce gradient text, tiny form fonts, or decorative motion in the assessment.
- Don't treat inherited homepage readiness claims as newly verified data.

Existing kicker text and some legacy decorative glyphs were retained to honor content preservation; they are not patterns to introduce on new surfaces.
