# UX/UI capture template

## Screen specification

- Screen ID and neutral name
- Route pattern; role; viewport; preconditions
- User goal and entry/exit paths
- Evidence IDs and classification
- Information hierarchy and landmarks
- Visible data and formatting
- Controls and actions
- Component inventory
- State matrix
- Responsive behavior
- Keyboard and accessibility observations
- Visual tokens observed
- UX risks and inconsistencies
- Proposed independent treatment
- Acceptance criteria
- Unknowns

## State matrix

| State | Trigger/precondition | Visible result | Available actions | Evidence |
|---|---|---|---|---|

Include only safely observed states. Mark plausible but untested states as unknown.

## Design-token evidence

| Category | Observed samples | Normalized observation | Proposed original token | Evidence |
|---|---|---|---|---|

Computed values may be implementation residue. Cluster repeated values before calling them tokens.

## Screenshot naming

Use `<evidence-id>--<screen-id>--<state>--<viewport>.png`. Redact before commit. Record whether a crop omits context relevant to the claim.
