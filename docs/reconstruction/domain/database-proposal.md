# Banco relacional proposto

> Modelo original para PostgreSQL; não descreve o banco interno do alvo.

O schema de aplicação proposto é `app`; schemas gerenciados do Supabase ficam excluídos e a Data API não é o caminho do frontend. A especificação detalhada, ainda sujeita à aprovação, está em [database-architecture.md](../../architecture/database-architecture.md). Os identificadores abaixo são públicos somente quando explicitamente expostos pelo contrato; IDs técnicos de infraestrutura podem ser `bigint`.

## Fundação SaaS

| Tabela                                                         | Finalidade e restrições principais                    |
| -------------------------------------------------------------- | ----------------------------------------------------- |
| `tenants`                                                      | organização raiz, timezone e estado                   |
| `units`                                                        | unidade; unique `(tenant_id, slug)`                   |
| `users`                                                        | identidade autenticável; PII protegida                |
| `memberships`                                                  | usuário × tenant/unidade × estado                     |
| `roles`, `permissions`, `role_permissions`, `membership_roles` | RBAC tenant-scoped                                    |
| `entitlements`                                                 | capacidade de plano, vigência e origem                |
| `audit_events`                                                 | append-only, ator, ação, recurso, motivo e correlação |
| `idempotency_keys`                                             | deduplicação de comandos tenant-scoped                |
| `outbox_events`, `inbox_events`                                | entrega idempotente de eventos                        |

### F2: constraints e ownership obrigatórios

- `users` é global e usa UUIDv7; `memberships` liga usuário a tenant; `membership_units` e `membership_roles` explicitam escopo;
- a baseline Laravel/Fortify preserva `email` e `password`, adiciona `email_normalized`, e coordena `HasUuids`, `passkeys.user_id`, `sessions.user_id`, reset tokens, 2FA, factories e testes antes do primeiro migrate remoto;
- `roles` são tenant-scoped; `permissions` são catálogo global; `entitlements` não substituem RBAC;
- `membership_roles` tem `id` UUIDv7 histórico, FKs compostas de tenant/membership/role/unit, `scope_kind`, `assignment_scope`, `unit_id`, `lock_version` e `revoked_at`; índice único parcial ativo é criado com `DB::statement` no PostgreSQL 15+;
- `entitlements` têm `trial/active/grace/suspended/expired/revoked`, `starts_at NOT NULL`, `ends_at > starts_at` e `quantity >= 0`;
- vigência é decidida por serviço/query com `starts_at <= :as_of AND (ends_at IS NULL OR ends_at > :as_of)`; índice parcial não avalia `now()` e suas bordas exigem teste;
- todas as tabelas tenant-owned possuem `tenant_id NOT NULL`; `unit_id` acompanha fatos de unidade;
- pivôs usam FKs compostas ou validação equivalente para impedir cruzamento de tenant;
- `audit_events` é append-only por trigger PostgreSQL `audit_events_append_only_guard`, owned pela role de migration, com runtime sem `UPDATE/DELETE`/bypass; `idempotency_keys` exige `tenant_id` F2; outbox/inbox usam `event_id` e `(consumer,event_id)`;
- outbox persiste status, versão do agregado, ator, correlation/causation e tentativas; `after_commit=true` ou `dispatchAfterCommit` é gate antes de uso;
- timestamps são `timestamptz` UTC; timezone IANA e `lock_version` são explícitos;
- bootstrap pré-migrate deve criar `app`, configurar `DB_SCHEMA=app`, `search_path=app,public`, TLS, DSN direto/pooler, e mover infra Laravel para `app` sem apagar migrations scaffold;
- PostgreSQL CI é obrigatório; SQLite apenas fast loop.

## Operação

| Contexto  | Tabelas propostas                                                                                          |
| --------- | ---------------------------------------------------------------------------------------------------------- |
| CRM       | `customers`, `customer_contacts`, `addresses`, `tags`, `customer_tags`, `consents`                         |
| Workforce | `professionals`, `professional_units`, `professional_services`, `availability_rules`                       |
| Catálogo  | `services`, `products`, `categories`, `brands`, `price_versions`, `service_policies`                       |
| Agenda    | `appointment_series`, `appointments`, `appointment_items`, `appointment_status_history`, `schedule_blocks` |
| Vendas    | `sale_categories`, `sales`, `sale_items`, `sale_status_histories`, `appointment_sale_links`               |
| Estoque   | `inventory_items`, `stock_movements`, `lots`, `stock_reservations`                                         |

## Financeiro e integrações

| Contexto       | Tabelas propostas                                                                                          |
| -------------- | ---------------------------------------------------------------------------------------------------------- |
| Pagamentos     | `checkout_sessions`, `payment_intents`, `payments`, `payment_allocations`, `refunds`                     |
| Ledger         | `financial_obligations`, `settlements`, `financial_accounts`, `account_movements`, `reconciliations`       |
| Caixa          | `cash_sessions`, `cash_counts`, `cash_session_events`                                                      |
| Comissão       | `commission_policy_versions`, `commission_accruals`, `commission_batches`, `commission_adjustments`        |
| Fiscal         | `fiscal_document_intents`, `fiscal_documents`, `fiscal_document_events`                                    |
| Relacionamento | `message_intents`, `message_deliveries`, `campaigns`, `campaign_members`, `reviews`                        |
| Analytics      | `metric_definitions`, `metric_snapshots`, `report_runs`, `export_jobs`, `goals`, `goal_progress_snapshots` |

## Padrões obrigatórios

- PK UUIDv7 para users/agregados; IDs técnicos internos podem ser bigint; toda tabela tenant-owned inclui `tenant_id` não nulo;
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
- outbox: `(status, lease_until, available_at, id)` com claim/reaper e locking seguro.

Jobs Laravel de infraestrutura não ganham `tenant_id` nesta fase: quando um job é tenant-aware, o payload/contexto carrega `tenant_id`, `unit_id`, correlation ID e versão, com autorização revalidada no worker. Índices de `jobs` seguem a tabela scaffold/queue e não são índices de domínio.

## Validação e ADRs pendentes

Validar dois tenants com unidades homônimas, FK cross-tenant, papéis unit/tenant-wide, membership revogada, corrida de idempotency/outbox, prepared statements no Session Pooler, DDL pela conexão direta, restore e backfill expand-contract.

Estratégia de RLS, particionamento de ledgers/eventos, busca de PII, isolamento por schema versus coluna, warehouse analítico e retenção fiscal precisam de decisão formal após volume e requisitos legais.

## Comandas por categoria e fechamento (Proposed — ADR-003)

| Tabela | Ownership/finalidade | Regras principais |
|---|---|---|
| `sale_categories` | configuração unit-owned no MVP | `tenant_id` + `unit_id` obrigatórios, nome/chave, ativo/inativo, tipos permitidos, cliente/agendamento e `uniqueness_scope`; inativar em vez de apagar |
| `sales` | fonte transacional | cliente/agendamento nullable, ciclo, moeda, totais em minor units, `open_context_key`, snapshots obrigatórios de `category_key`/`category_name`, versão |
| `sale_items` | itens capturados | snapshots de nome/preço/moeda, tipo, quantidade, desconto e origem |
| `sale_status_histories` | histórico append-only | transição, ator, motivo e correlação |
| `appointment_sale_links` | única fonte de verdade Agenda/Vendas | várias comandas por agendamento; no máximo um link ativo por `sale_id` (unique parcial), mesmo tenant/unidade, vínculo auditável |
| `closing_sessions` | agregado de tentativa de fechamento | seleção, `closing_subject`, unidade/moeda, versões, totais esperados, idempotência e `draft/ready/processing/completed/cancelled/failed` |

`open_context_key` é coluna materializada pelo backend: `customer:{uuid}`, `appointment:{uuid}` ou `reference:{normalized}`; para `none`, nulo. Unique parcial recomendado: `(tenant_id, unit_id, sale_category_id, open_context_key) WHERE status IN ('draft','open','ready_to_bill') AND open_context_key IS NOT NULL`. A transação e o índice decidem a corrida, não a UI. `Appointment 0..N Sale`; `Sale 0..1 Appointment` no MVP, garantido exclusivamente por unique parcial de link ativo em `appointment_sale_links.sale_id`; `sales` não possui coluna de vínculo direto.

`ClosingSession` fixa `closing_subject`: o mesmo `customer_id` não nulo ou a mesma `reference_context` normalizada. Nunca mistura clientes/referências, unidade ou moeda. A sessão finaliza comandas e gera recibo interno; não processa ou registra pagamento externo nesta wave. As invariantes canônicas estão no [ADR-003](../../adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md).

FKs compostas ou validação equivalente impedem categoria, cliente, agendamento, unidade e sale cross-tenant. Índices de listagem começam por tenant/unidade/status; histórico e fechamentos não são apagados; categorias são inativadas. Validar entidade de mesa/referência, cliente anônimo, futura decisão de Caixa e retenção de observações.
