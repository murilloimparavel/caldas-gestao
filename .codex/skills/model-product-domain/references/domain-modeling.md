# Domain and data modeling checklist

## Evidence-to-model matrix

| Model element | Classification | Supporting claims | Rationale | Alternatives / unknowns |
|---|---|---|---|---|

## Aggregate specification

- purpose and boundary;
- root and owned entities/value objects;
- commands and authorized actors;
- invariants and transaction boundary;
- lifecycle/state machine;
- emitted events and synchronous effects;
- conflict, retry, reversal, and audit behavior.

## State transition table

| From | Command | Guard / role | To | Synchronous effects | Events | Reversal |
|---|---|---|---|---|---|---|

## Table proposal

For each table document purpose, ownership context, tenant scope, primary key, foreign keys, important columns/types, nullability, unique/check constraints, indexes, history strategy, retention/deletion, and PII classification.

Distinguish operational source-of-truth tables, immutable ledgers/audit records, outbox/inbox delivery state, and read models or analytics projections.

Do not normalize or denormalize by habit. Justify the choice using invariants, query patterns, update frequency, volume, and consistency needs.

## Validation scenarios

Test the proposal mentally against relevant cases among duplicate commands, concurrent edits, tenant isolation, role changes, timezone boundaries, partial failure, retry, cancellation/estorno, historical reporting, anonymization, and restore/audit needs.
