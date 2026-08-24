# Banco relacional proposto

> Modelo original para PostgreSQL; não descreve o banco interno do alvo.

## Fundação SaaS

| Tabela | Finalidade e restrições principais |
|---|---|
| `tenants` | organização raiz, timezone e estado |
| `units` | unidade; unique `(tenant_id, slug)` |
| `users` | identidade autenticável; PII protegida |
| `memberships` | usuário × tenant/unidade × estado |
| `roles`, `permissions`, `role_permissions`, `membership_roles` | RBAC tenant-scoped |
| `entitlements` | capacidade de plano, vigência e origem |
| `audit_events` | append-only, ator, ação, recurso, motivo e correlação |
| `outbox_events`, `inbox_events` | entrega idempotente de eventos |

## Operação

| Contexto | Tabelas propostas |
|---|---|
| CRM | `customers`, `customer_contacts`, `addresses`, `tags`, `customer_tags`, `consents` |
| Workforce | `professionals`, `professional_units`, `professional_services`, `availability_rules` |
| Catálogo | `services`, `products`, `categories`, `brands`, `price_versions`, `service_policies` |
| Agenda | `appointment_series`, `appointments`, `appointment_items`, `appointment_status_history`, `schedule_blocks` |
| Vendas | `sales`, `sale_items`, `sale_benefit_allocations`, `sale_status_history` |
| Estoque | `inventory_items`, `stock_movements`, `lots`, `stock_reservations` |

## Financeiro e integrações

| Contexto | Tabelas propostas |
|---|---|
| Pagamentos | `payment_intents`, `payments`, `payment_allocations`, `refunds` |
| Ledger | `financial_obligations`, `settlements`, `financial_accounts`, `account_movements`, `reconciliations` |
| Caixa | `cash_sessions`, `cash_counts`, `cash_session_events` |
| Comissão | `commission_policy_versions`, `commission_accruals`, `commission_batches`, `commission_adjustments` |
| Fiscal | `fiscal_document_intents`, `fiscal_documents`, `fiscal_document_events` |
| Relacionamento | `message_intents`, `message_deliveries`, `campaigns`, `campaign_members`, `reviews` |
| Analytics | `metric_definitions`, `metric_snapshots`, `report_runs`, `export_jobs`, `goals`, `goal_progress_snapshots` |

## Padrões obrigatórios

- PK UUID/ULID; toda tabela tenant-owned inclui `tenant_id` não nulo;
- índices começam por `tenant_id` e seguem os filtros dominantes;
- unique/check constraints incluem tenant e estados válidos;
- dinheiro em `bigint` minor units + `currency char(3)`;
- `timestamptz` para instantes, `date` para competência civil e timezone explícito;
- edição concorrente por `version bigint`;
- ledgers/status/auditoria são append-only; correções usam registros compensatórios;
- soft delete apenas onde necessário; financeiro/fiscal preservam retenção legal;
- PII cifrada ou tokenizada por sensibilidade, com índices derivados quando busca for necessária.

## Índices críticos

- agenda: `(tenant_id, unit_id, professional_id, starts_at, ends_at)`;
- cliente: contatos normalizados com política de unicidade tenant-scoped;
- vendas/pagamentos: `(tenant_id, status, occurred_at)` e IDs externos únicos por provedor;
- movimentos: `(tenant_id, account_id, effective_at, id)`;
- jobs: `(tenant_id, status, created_at)`;
- outbox: `(status, available_at)` com locking seguro.

## ADRs pendentes

Estratégia de RLS, particionamento de ledgers/eventos, busca de PII, isolamento por schema versus coluna, warehouse analítico e retenção fiscal precisam de decisão formal após volume e requisitos legais.
