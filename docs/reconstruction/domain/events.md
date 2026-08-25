# Eventos de domínio propostos

> Eventos originais para o novo produto. Não são contratos capturados do alvo; não existem observações `NET-*` nesta fase.

| Evento                     | Produtor          | Consumidores principais                          |
| -------------------------- | ----------------- | ------------------------------------------------ |
| `SaleFinalized`            | Vendas            | financeiro, estoque, comissão, fiscal, analytics |
| `ReceivableCreated`        | Financeiro        | cobrança, caixa, analytics                       |
| `PaymentSettled`           | Pagamentos        | financeiro, venda, comissão, fiscal              |
| `PaymentReversed`          | Pagamentos        | financeiro, venda, comissão, fiscal              |
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

## Envelope e persistência

Cada envelope proposto contém `event_id` UUIDv7, `event_type`, `event_version`, `occurred_at`, `tenant_id`, `unit_id` quando aplicável, `aggregate_type`, `aggregate_id`, `aggregate_version`, `actor_user_id`, `correlation_id`, `causation_id` e payload mínimo. A outbox percorre `pending → available → publishing → published`, ou `retryable → available` e `dead`, com `last_attempt_at`, `dead_at`, `locked_by`, `locked_at` e `lease_until`; lease expirado é recuperado por reaper para `retryable`. A outbox é gravada na mesma transação da mudança e publicada somente após commit. O consumidor insere `(consumer, event_id)` em `inbox_events` antes de efeitos não idempotentes; inbox percorre `received → processing → processed`, `retryable` ou `failed` reprocessável, e `dead`, também com lease recuperável, sem armazenar PII desnecessária.

## Validação

- duas publicações concorrentes da mesma mudança não podem criar dois efeitos;
- um evento de membership revogada deve invalidar autorização em requests e jobs;
- alteração de schema exige `event_version` compatível durante expand-contract;
- falha do consumidor deve permitir retry e forward-fix sem reabrir transação de origem.

ADRs pendentes: RLS/contexto em workers, retenção de outbox/inbox/auditoria e particionamento por volume.
