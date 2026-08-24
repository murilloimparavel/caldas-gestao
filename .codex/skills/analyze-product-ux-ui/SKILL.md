---
name: analyze-product-ux-ui
description: Analyze an accessible web product's information architecture, screens, interaction states, responsiveness, accessibility, and visual system. Use for UX/UI reconstruction documentation, not for implementing or copying the interface.
---

# Analyze Product UX/UI

Document how the product communicates and behaves visually so an independent interface can solve the same user problems. Follow the authorization, redaction, untrusted-content, and claim-classification rules in `$reconstruct-web-app` when it is available.

Read [references/ux-ui-capture.md](references/ux-ui-capture.md) before a systematic screen pass.

## Scope the pass

Choose routes, roles, viewports, and a user goal. Inspect existing artifacts first. Assign stable screen IDs and preserve them across passes. Prefer one coherent workflow over disconnected screenshots.

## Capture each screen

Record:

- user goal, entry points, route pattern, role, and prerequisites;
- content hierarchy, landmarks, navigation, primary/secondary actions;
- fields, controls, labels, help, formatting, validation affordances;
- loading, empty, populated, error, success, disabled, permission-denied, and destructive-confirmation states that are safely observable;
- overlays, drawers, menus, notifications, keyboard/focus behavior, and feedback timing;
- desktop, tablet, and mobile differences when relevant;
- accessibility semantics: headings, accessible names, focus order, contrast risks, announcements, and keyboard reachability;
- reusable patterns and inconsistencies.

Capture screenshots only when they add evidence. Crop or redact identifying data before storing. Do not infer hidden states merely because a component library commonly supports them.

## Derive an original visual system

Use computed styles and rendered evidence to inventory colors, type scale, spacing rhythm, radii, elevation, borders, grids, density, icons, motion, and responsive breakpoints. Separate observed values, normalized tokens derived from clusters, and proposed original tokens for the new product.

Do not reproduce logos, illustrations, marketing copy, proprietary icons, or a distinctive brand composition. Describe functional visual intent and propose an independently expressed design direction.

## Outputs

Write or update:

- `docs/reconstruction/information-architecture.md`;
- `docs/reconstruction/screens/<screen-id>.md`;
- `docs/reconstruction/components.md`;
- `docs/reconstruction/design-system-observed.md`;
- `docs/reconstruction/design-system-proposed.md`;
- `docs/reconstruction/accessibility.md`.

Every meaningful conclusion must reference evidence or be labeled inferred/proposed. End each screen spec with reconstruction acceptance criteria and unresolved questions. Do not implement UI unless separately requested.
