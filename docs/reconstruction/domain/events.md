# Eventos de domínio propostos

| Evento | Produtor | Consumidores principais |
|---|---|---|
| `SaleFinalized` | Vendas | financeiro, estoque, comissão, fiscal, analytics |
| `ReceivableCreated` | Financeiro | cobrança, caixa, analytics |
| `PaymentSettled` | Pagamentos | financeiro, venda, comissão, fiscal |
| `PaymentReversed` | Pagamentos | financeiro, venda, comissão, fiscal |
| `AccountMovementPosted` | Ledger | saldos, caixa, relatórios |
| `CashSessionOpened` | Caixa | auditoria |
| `CashSessionClosed` | Caixa | reconciliação, auditoria |
| `CommissionAccrued` | Comissão | aprovação, relatórios |
| `CommissionPaid` | Comissão | financeiro, relatórios |
| `FiscalDocumentAuthorized` | Fiscal | venda, cliente, relatórios |
| `FiscalDocumentRejected` | Fiscal | fila operacional/alertas |

Todos carregam IDs opacos, tenant/unidade, versão, timestamps e correlação; não carregam PII, tokens ou payload fiscal bruto. Consumidores usam inbox/outbox e idempotência.
