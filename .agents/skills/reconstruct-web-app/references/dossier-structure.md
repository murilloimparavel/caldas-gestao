# Dossier structure

Use this layout selectively; create files when there is evidence for them rather than empty placeholders.

```text
docs/reconstruction/
├── manifest.md
├── claim-ledger.md
├── information-architecture.md
├── components.md
├── design-system-observed.md
├── design-system-proposed.md
├── accessibility.md
├── behavior-rules.md
├── roles-permissions.md
├── network-observations.md
├── api-proposal.md
├── open-questions.md
├── reconstruction-backlog.md
├── screens/<screen-id>.md
├── flows/<flow-id>.md
├── evidence/<evidence-id>.<ext>
└── domain/
    ├── glossary.md
    ├── contexts.md
    ├── model.md
    ├── state-machines.md
    ├── events.md
    ├── database-proposal.md
    └── data-governance.md
```

## Manifest minimum

- authorized target and account/environment type;
- allowed mutation level;
- observation date, roles, devices, and viewports;
- modules in/out of scope;
- artifact index and coverage status;
- redaction statement and known sensitive-data risks;
- major unknowns and recommended next passes.

## Claim ledger row

| ID | Classification | Claim | Context | Evidence | Confidence | Implication / next check |
|---|---|---|---|---|---|---|

## Coverage states

Use `not started`, `partial`, `happy path`, `negative paths`, or `sufficient for scoped reconstruction`. Never use `complete` without listing tested roles, states, and viewports.
