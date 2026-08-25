# Eventos de domínio propostos

> Eventos originais para o novo produto. Não são contratos capturados do alvo; não existem observações `NET-*` nesta fase.

| Evento                     | Produtor          | Consumidores principais                          | Exposição |
| -------------------------- | ----------------- | ------------------------------------------------ | --------- |
| `SaleCategoryCreated`      | Vendas            | configuração, auditoria                         | público interno |
| `SaleCategoryUpdated`      | Vendas            | configuração, auditoria                         | público interno |
| `SaleCategoryDeactivated`  | Vendas            | UI de abertura, auditoria                        | público interno |
| `SaleOpened`               | Vendas            | agenda, CRM, analytics                           | público interno |
| `SaleItemAdded`            | Vendas            | auditoria, projeções; estoque futuro             | interno/audit-only no MVP |
| `SaleItemRemoved`          | Vendas            | auditoria, projeções                             | interno/audit-only no MVP |
| `SaleStatusChanged`        | Vendas            | timeline, auditoria                              | público interno |
| `AppointmentSaleLinked`    | Vendas/Agenda     | detalhe do agendamento, analytics                 | público interno |
| `CheckoutSessionCreated`   | Cobrança          | auditoria, pagamentos                            | público interno |
| `CheckoutSessionCompleted` | Cobrança          | financeiro, analytics                            | público interno |
| `CheckoutSessionFailed`    | Cobrança          | fila operacional, auditoria                      | público interno |
| `PaymentIntentCreated`     | Cobrança          | pagamentos                                       | público interno |
| `PaymentRecorded`          | Pagamentos        | financeiro, venda                                | público interno |
| `PaymentAllocated`         | Pagamentos        | saldo da venda, financeiro, analytics             | público interno |
| `RefundRecorded`           | Pagamentos        | financeiro, venda, estoque, comissão              | público interno |
| `SaleFinalized`            | Vendas            | financeiro, estoque, comissão, fiscal, analytics | público interno |
| `SaleCancelled`            | Vendas            | auditoria, analytics                             | público interno |
| `SaleAdjusted`             | Vendas            | financeiro, analytics                            | público interno |
| `ReceivableCreated`        | Financeiro        | cobrança, caixa, analytics                       | público interno |
| `PaymentSettled`           | Pagamentos        | financeiro, venda, comissão, fiscal              | público interno |
| `PaymentReversed`          | Pagamentos        | financeiro, venda, comissão, fiscal              | público interno |
| `AccountMovementPosted`    | Ledger            | saldos, caixa, relatórios                        |
| `CashSessionOpened`        | Caixa             | auditoria                                        |
| `CashSessionClosed`        | Caixa             | reconciliação, auditoria                         |
| `CommissionAccrued`        | Comissão          | aprovação, relatórios                            |
| `CommissionPaid`           | Comissão          | financeiro, relatórios                           |
| `FiscalDocumentAuthorized` | Fiscal            | venda, cliente, relatórios                       |
| `FiscalDocumentRejected`   | Fiscal            | fila operacional/alertas                         |
| `MembershipGranted`        | Identidade/acesso | auditoria, cache de autorização, notificações    |
| `MembershipActivated`      | Identidade/acesso | auditoria, cache de autorização, sessões         |
| `MembershipRevoked`        | Identidade/acesso | sessões, cache, auditoria                        |
| `MembershipReinvited`      | Identidade/acesso | auditoria, entrega de convite, cache             |
| `EntitlementChanged`       | Plataforma        | autorização, paywall, auditoria                  |
| `RoleAssignmentChanged`    | Identidade/acesso | cache de autorização, auditoria                  |

Todos carregam IDs opacos, tenant/unidade, versão, timestamps e correlação; não carregam PII, tokens ou payload fiscal bruto. Consumidores usam inbox/outbox e idempotência.

“Público interno” significa contrato versionado entre módulos do próprio produto, não endpoint público. Eventos `SaleItemAdded` e `SaleItemRemoved` são `internal/audit-only` no MVP: servem à timeline, auditoria e projeções internas e não devem disparar baixa de estoque/comissão antes de esses contextos possuírem contrato próprio. `SaleCategoryUpdated` registra alteração de nome/política e nunca reescreve `category_key_snapshot`/`category_name_snapshot` de sales existentes. `SaleFinalized`, `PaymentAllocated`, `RefundRecorded` e `CheckoutSessionCompleted` são eventos internos versionados para consumidores de domínio.

## Envelope e persistência

Cada envelope proposto contém `event_id` UUIDv7, `event_type`, `event_version`, `occurred_at`, `tenant_id`, `unit_id` quando aplicável, `aggregate_type`, `aggregate_id`, `aggregate_version`, `actor_user_id`, `correlation_id`, `causation_id` e payload mínimo. A outbox percorre `pending → available → publishing → published`, ou `retryable → available` e `dead`, com `last_attempt_at`, `dead_at`, `locked_by`, `locked_at` e `lease_until`; lease expirado é recuperado por reaper para `retryable`. A outbox é gravada na mesma transação da mudança e publicada somente após commit. O consumidor insere `(consumer, event_id)` em `inbox_events` antes de efeitos não idempotentes; inbox percorre `received → processing → processed`, `retryable` ou `failed` reprocessável, e `dead`, também com lease recuperável, sem armazenar PII desnecessária.

## Validação

- duas publicações concorrentes da mesma mudança não podem criar dois efeitos;
- um evento de membership revogada deve invalidar autorização em requests e jobs;
- alteração de schema exige `event_version` compatível durante expand-contract;
- falha do consumidor deve permitir retry e forward-fix sem reabrir transação de origem.

- checkout repetido com a mesma chave não duplica pagamento ou alocação;
- eventos preservam `sale_id`, categoria e unidade para reconciliação, sem PII desnecessária;
- `SaleFinalized` pode ser consumido por estoque/comissão/fiscal sem acoplar suas transações ao checkout.

ADRs pendentes: RLS/contexto em workers, retenção de outbox/inbox/auditoria e particionamento por volume.
