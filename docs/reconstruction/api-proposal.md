# API proposta para o novo produto

> Proposta independente baseada em requisitos observáveis. Não representa a API interna do produto estudado.

## Convenções

- prefixo `/v1`, JSON, IDs opacos e timestamps ISO 8601;
- tenant derivado da sessão; `unit_id` explícito quando necessário;
- dinheiro como `{ amount_minor, currency }`;
- paginação por cursor e filtros normalizados;
- `Idempotency-Key` obrigatório em comandos financeiros e externos;
- `If-Match`/versão esperada em edição concorrente;
- erros `{ code, message, field_errors, correlation_id }`;
- autorização por capacidade e escopo validada no servidor;
- comandos longos retornam job com estado consultável.

## Recursos principais

| Contexto | Operações propostas |
|---|---|
| Clientes | `GET/POST /customers`, `GET/PATCH /customers/{id}` |
| Profissionais | `/professionals`, `/users`, `/roles`, `/permissions` |
| Catálogo | `/services`, `/products`, `/categories`, `/packages` |
| Agenda | `/availability:search`, `/appointments`, `/appointment-series`, `/schedule-blocks` |
| Vendas | `/sales`, `/sales/{id}/items`, `/sales/{id}:finalize`, `/sales/{id}:cancel` |
| Pagamentos | `/payment-intents`, `/payments`, `/payments/{id}:refund` |
| Estoque | `/stock-movements`, `/inventory-balances`, `/lots` |
| Financeiro | `/obligations`, `/account-movements`, `/cash-sessions`, `/reconciliations` |
| Comissões | `/commission-accruals`, `/commission-batches` |
| Fiscal | `/fiscal-documents`, `/fiscal-documents/{id}:retry`, `:cancel` |
| Analytics | `/metrics:query`, `/report-runs`, `/export-jobs`, `/goals` |
| Relacionamento | `/message-intents`, `/campaigns`, `/reviews`, `/consents` |
| Plataforma | `/units`, `/settings`, `/entitlements`, `/api-credentials`, `/audit-events` |

## Exemplo de comando original

```json
POST /v1/appointments
{
  "unit_id": "opaque-id",
  "customer_id": "opaque-id",
  "starts_at": "2026-08-24T14:00:00-03:00",
  "items": [{"service_id":"opaque-id","professional_id":"opaque-id"}],
  "expected_availability_version": 12
}
```

Respostas propostas: `201` criado; `409 schedule_conflict`; `422 validation_failed`; `403 capability_denied`; replay idempotente retorna o mesmo recurso.

## Eventos/webhooks

- outbox/inbox e assinatura rotacionável;
- payload mínimo sem PII desnecessária;
- `event_id`, `event_type`, `occurred_at`, `tenant_id`, `resource_id`, `version`;
- retries com backoff e dead-letter; consumidor idempotente;
- webhook externo por assinatura, timestamp e proteção contra replay.

## Pendências de validação

Contratos finais dependem de testes sintéticos de concorrência, recorrência, pagamentos parciais, estorno, fiscal, comissão, estoque e messaging. Ver `network-observations.md`.
