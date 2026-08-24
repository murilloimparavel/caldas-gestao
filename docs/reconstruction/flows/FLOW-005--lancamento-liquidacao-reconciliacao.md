# FLOW-005 — Lançamento, liquidação e reconciliação

## Fluxo proposto a partir das evidências

```text
Fonte operacional (venda/despesa/transferência)
        ↓
Obrigação a receber/pagar
        ↓ disponibilidade/vencimento
Liquidação por um ou mais pagamentos
        ↓
Movimento imutável na conta
        ↓
Conciliação/fechamento de caixa
        ↓
Projeções, relatórios, comissão e fiscal
```

## Regras

- competência, vencimento/disponibilidade e pagamento são datas independentes;
- bruto, taxas e líquido são preservados;
- obrigação, pagamento e movimento de conta não são o mesmo agregado;
- liquidação parcial deve manter saldo restante;
- pagamento duplicado é impedido por idempotência e referência externa;
- correção usa estorno/ajuste compensatório, nunca edição destrutiva;
- fechamento de caixa bloqueia alterações diretas no período fechado;
- reabertura exige papel elevado, motivo e auditoria;
- comissão e nota consomem eventos versionados, sem travar a transação da venda.

## Testes futuros seguros

Somente em tenant sintético: recebimento parcial, pagamento dividido, atraso, desbloqueio, estorno, divergência de caixa, falha fiscal e reprocessamento de comissão.
