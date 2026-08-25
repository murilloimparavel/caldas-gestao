# FLOW-004 — Comanda, faturamento e pagamento

## Fluxo observado/inferido

```text
Nova comanda
  ├─ Salvar → comanda persistida sem faturamento final (Inferred)
  └─ Faturar → inicia cobrança/fechamento (Inferred)
                    ↓
          pagamento aberto/bloqueado/disponível/atrasado/pago
                    ↓
                finalização
```

Somente os controles e estados visíveis foram observados. Nenhuma transição foi executada.

## Máquinas de estado propostas

```text
Sale: draft → open → ready_to_bill → finalized
                 ↘ cancelled
finalized → adjusted

Payment: created → available → processing → paid
             ↘ blocked      ↘ failed
             ↘ overdue
paid → partially_refunded → refunded

CheckoutSession: draft → ready → processing → completed
                              └────→ cancelled/failed
```

Venda, tentativa de checkout e pagamento são agregados separados: uma venda pode ter múltiplos pagamentos e um pagamento não deve reescrever itens/totais. Pagamento parcial é saldo derivado de `payment_allocations`, não estado de Sale/PaymentIntent. A sessão fixa unidade, moeda, seleção, versões e `checkout_subject` uniforme.

## Invariantes

- total bruto = soma de quantidade × preço capturado;
- total líquido = bruto − descontos − créditos − cashback + acréscimos;
- dinheiro em decimal exato/centavos e moeda explícita;
- cada item captura serviço/produto, profissional, preço e regra vigente;
- saldo de pacote/assinatura/crédito é consumido por ledger idempotente;
- faturar exige versão esperada da comanda e idempotency key;
- pagamento, comissão, estoque e fiscalidade são efeitos coordenados por eventos/outbox;
- exclusão lógica nunca apaga lançamento financeiro; correção usa ajuste/estorno;
- valor zero exige origem explicável e auditável.

## Desconhecidos críticos

- diferença exata entre Salvar e Faturar;
- quando a comanda vira Finalizada;
- significado e transições de Bloqueado/Disponível;
- parcelamento, múltiplas formas e troco;
- vínculo reverso com agendamento;
- emissão fiscal, comissão e estoque em falha parcial.

## Decisão proposta — categorias e fechamento consolidado (Proposed, ADR-003)

`Sale` é independente de agendamento e cliente. No MVP, `Sale 0..1 Appointment`, enquanto um `Appointment` pode ter várias sales. `sale_categories` é unit-scoped e limita tipos de item, política de contexto e unicidade. Uma pessoa/mesa pode possuir simultaneamente várias comandas, desde que sejam categorias distintas ou contextos distintos. O unique parcial usa `open_context_key` somente nos estados ativos; categoria `none` permite duplicidade.

No mobile, `Comandas abertas` agrupa por cliente, mesa ou referência e permite selecionar todas ou parte das comandas. `CheckoutSession` fixa seleção, versões, unidade, moeda e `checkout_subject`; este deve ser o mesmo `customer_id` não nulo ou a mesma `reference_context`, nunca clientes/referências diferentes. `FinalizeConsolidatedCheckout` trava em ordem determinística, cria `payment_intents`, `payments` e `payment_allocations`, mantém histórico individual e finaliza apenas saldos zerados. Pagamento parcial é saldo derivado e mantém `Sale` aberta. Repetição idempotente não duplica. Refund/ajuste cria registro novo.

As tabelas propostas são `sale_categories`, `sales`, `sale_items`, `sale_status_histories`, `appointment_sale_links`, `checkout_sessions`, `payment_intents`, `payments`, `payment_allocations` e `refunds`. Esses nomes são desenho independente, não observação do schema do produto referência.

Desconhecidos adicionais: política de mesa/referência, cliente anônimo, fechamento com pagamento liquidado e retenção LGPD de observações/referências.

As invariantes normativas desta proposta são as [invariantes canônicas do ADR-003](../../adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md).
