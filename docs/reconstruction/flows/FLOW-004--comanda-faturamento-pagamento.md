# FLOW-004 — Comanda, faturamento e fechamento consolidado

## Fluxo observado/inferido

```text
Nova comanda
  ├─ Salvar → comanda persistida sem faturamento final (Inferred)
  └─ Finalizar → inicia fechamento consolidado (Proposed)
                    ↓
              revisão e confirmação
                    ↓
                finalização/recibo interno
```

Somente os controles e estados visíveis foram observados. Nenhuma transição foi executada.

## Máquinas de estado propostas

```text
Sale: draft → open → ready_to_bill → finalized
                 ↘ cancelled
finalized → adjusted

ClosingSession: draft → ready → processing → completed
                          └────→ cancelled/failed
```

Venda e operação de fechamento são agregados separados: a seleção de comandas não reescreve itens/totais. A sessão fixa unidade, moeda, seleção, versões e `closing_subject` uniforme. O Caldas não processa nem registra pagamento nesta wave; qualquer recebimento ocorre fora do app.

## Invariantes

- total bruto = soma de quantidade × preço capturado;
- total líquido = bruto − descontos + acréscimos;
- dinheiro em decimal exato/centavos e moeda explícita;
- cada item captura serviço/produto, profissional, preço e regra vigente;
- fechar exige versão esperada da comanda e idempotency key;
- comissão, estoque e fiscalidade são efeitos futuros coordenados por eventos/outbox;
- fechamento não apaga histórico e qualquer correção usa ajuste auditado;
- valor zero exige origem explicável e auditável.

## Desconhecidos críticos

- diferença exata entre Salvar e Faturar;
- quando a comanda vira Finalizada;
- significado e transições de Bloqueado/Disponível;
- futura decisão de Caixa sobre registrar recebimento externo;
- vínculo reverso com agendamento;
- emissão fiscal, comissão e estoque em falha parcial.

## Decisão proposta — categorias e fechamento consolidado (Proposed, ADR-003)

`Sale` é independente de agendamento e cliente. No MVP, `Sale 0..1 Appointment`, enquanto um `Appointment` pode ter várias sales. `sale_categories` é unit-scoped e limita tipos de item, política de contexto e unicidade. Uma pessoa/mesa pode possuir simultaneamente várias comandas, desde que sejam categorias distintas ou contextos distintos. O unique parcial usa `open_context_key` somente nos estados ativos; categoria `none` permite duplicidade.

No mobile, `Comandas abertas` agrupa por cliente, mesa ou referência e permite selecionar todas ou parte das comandas. `ClosingSession` fixa seleção, versões, unidade, moeda e `closing_subject`; este deve ser o mesmo `customer_id` não nulo ou a mesma `reference_context`, nunca clientes/referências diferentes. `FinalizeConsolidatedSales` trava em ordem determinística, revisa totais, mantém histórico individual, finaliza a seleção e gera recibo interno. Repetição idempotente não duplica. Não há gateway, cobrança, entidades de pagamento ou recebimento externo nesta wave.

As tabelas propostas são `sale_categories`, `sales`, `sale_items`, `sale_status_histories`, `appointment_sale_links` e `closing_sessions`. Esses nomes são desenho independente, não observação do schema do produto referência. Entidades de recebimento externo ficam fora do MVP.

Desconhecidos adicionais: política de mesa/referência, cliente anônimo, futura decisão de Caixa sobre registrar recebimento externo e retenção LGPD de observações/referências.

As invariantes normativas desta proposta são as [invariantes canônicas do ADR-003](../../adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md).
