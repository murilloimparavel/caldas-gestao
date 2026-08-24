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
```

Venda e pagamento são agregados separados: uma venda pode ter múltiplos pagamentos e um pagamento não deve reescrever itens/totais.

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
