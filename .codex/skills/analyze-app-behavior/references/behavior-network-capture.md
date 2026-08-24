# Behavior and network capture

## Flow specification

- Flow ID and user outcome
- Actors/roles and preconditions
- Synthetic test data used
- Ordered happy-path steps
- Alternate and recovery paths
- Validation and error matrix
- State transitions
- Cross-domain side effects
- Notifications/integrations
- Authorization observations
- Evidence-linked business rules
- Given/When/Then acceptance criteria
- Unknowns and unsafe/unperformed tests

## Rule table

| Rule ID | Classification | Preconditions | Rule/result | Evidence | Confidence | Negative case tested? |
|---|---|---|---|---|---|---|

## Sanitized request observation

```yaml
id: NET-001
classification: observed
trigger: "Neutral description of UI action"
operation: "Create appointment"
request:
  method: POST
  route_shape: /resource
  body_shape:
    field: string
response:
  status: 201
  body_shape:
    id: opaque-id
ui_effect: "New item appears in list"
redactions: [authorization, tenant-id, personal-values]
unknowns: [idempotency, full-error-set]
```

Never save a raw HAR from an authenticated production session unless the user explicitly needs it and a safe redaction process is available. Prefer a manually sanitized observation.

The proposed API should use product-owned naming, resources, errors, authorization, pagination, idempotency, and versioning. Link motivating observations without mirroring target endpoints by default.
