---
name: model-product-domain
description: Convert product evidence into an independent domain model, state machines, invariants, events, tenancy and authorization boundaries, and a proposed relational schema. Use after or alongside product analysis; do not claim the proposal reproduces the target's internal database.
---

# Model Product Domain

Design the model the new product needs from evidence and stated requirements. Never describe a proposed schema as the target application's actual database.

Read [references/domain-modeling.md](references/domain-modeling.md) before producing an ERD or schema proposal.

## Inputs and epistemic rules

Use screen specs, flows, sanitized network observations, business interviews, and existing project decisions. Cite claim IDs. For every entity, field, relationship, invariant, or event, classify it as observed, inferred, or proposed. Prefer unresolved questions over invented certainty.

## Model in this order

1. Build a glossary and identify actors, resources, transactions, policies, and value objects.
2. Separate bounded contexts and ownership.
3. Define aggregate boundaries and invariants that must be transactionally consistent.
4. Define lifecycle states, transitions, guards, roles, side effects, reversals, and terminal states.
5. Define domain events and consumers, distinguishing synchronous effects from eventual processing.
6. Propose an independent relational model with keys, cardinalities, constraints, indexes, history, and deletion/retention policy.
7. Validate against happy paths, failures, concurrency, idempotency, reporting, and audit needs.

## Non-negotiable SaaS concerns

- Make tenant and unit boundaries explicit; scope tenant-owned queries and uniqueness rules.
- Enforce authorization server-side independently of UI visibility.
- Model money using integer minor units or exact decimal with currency, never floating point.
- Model timestamps, business timezone, and date-only concepts deliberately.
- Preserve financial, inventory, commission, consent, and audit history through immutable records or explicit adjustments.
- Treat webhook and retry consumers as idempotent.
- Classify personal and sensitive data, retention, deletion/anonymization, purpose, consent, and access logging in proportion to LGPD risk.
- Do not put secrets or raw sensitive payloads in events, logs, fixtures, or documentation.

## Outputs

Write or update:

- `docs/reconstruction/domain/glossary.md`;
- `docs/reconstruction/domain/contexts.md`;
- `docs/reconstruction/domain/model.md`;
- `docs/reconstruction/domain/state-machines.md`;
- `docs/reconstruction/domain/events.md`;
- `docs/reconstruction/domain/database-proposal.md`;
- `docs/reconstruction/domain/data-governance.md`.

Use Mermaid ER diagrams only when they improve comprehension; accompany them with invariants and rationale. A table list alone is not a domain model. End with validation scenarios and modeling decisions that still require an ADR.
