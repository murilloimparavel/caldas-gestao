---
name: analyze-app-behavior
description: Investigate an authorized web application's workflows, validation, roles, state transitions, side effects, and sanitized network behavior. Use to produce functional and API reconstruction specs, not to discover vulnerabilities or extract private APIs wholesale.
---

# Analyze App Behavior

Turn observable behavior into testable functional requirements. Follow the clean-room, authorization, redaction, and evidence rules from `$reconstruct-web-app` when available.

Read [references/behavior-network-capture.md](references/behavior-network-capture.md) before capturing workflows or requests.

## Behavioral pass

For one user goal, document actor, role, preconditions, trigger, ordered steps, alternate paths, completion signal, inputs, validation timing, state changes, authorization evidence, observable side effects, and recovery behavior.

Use synthetic data in a test tenant for mutations. Before a potentially irreversible or externally visible action, stop and obtain specific authorization. Never trigger real payments, messages, invitations, deletions, exports of personal data, or production webhooks merely to observe them.

## Network pass

Capture only requests caused by an explicitly scoped interaction. Record sanitized contract shapes rather than raw sessions:

- semantic operation and UI trigger;
- method and normalized route template;
- path/query/body field names, types, optionality, and enum values observed;
- status and response shape;
- pagination, sorting, filtering, concurrency, idempotency, and error semantics when evidenced;
- correlation to visible UI changes.

Strip host-specific identifiers and all secrets. Do not copy authorization headers, cookies, signed URLs, tokens, analytics identifiers, or personal payload values. Treat endpoint names and payload semantics as observations; propose a clean independent API separately.

## Outputs

Write or update:

- `docs/reconstruction/flows/<flow-id>.md`;
- `docs/reconstruction/behavior-rules.md`;
- `docs/reconstruction/roles-permissions.md`;
- `docs/reconstruction/network-observations.md`;
- `docs/reconstruction/api-proposal.md`;
- `docs/reconstruction/open-questions.md`.

Use Given/When/Then acceptance criteria for stable behavior. Label negative cases not tested as unknown, not unsupported. Keep the observed network inventory separate from the proposed API to prevent accidental cloning.
