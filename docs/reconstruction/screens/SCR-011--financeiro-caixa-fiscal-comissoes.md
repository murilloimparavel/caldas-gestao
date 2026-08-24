# SCR-011 — Financeiro, caixa, fiscal e comissões

## Painel financeiro

Hierarquia observada:

1. resumo de vencimentos do dia;
2. saldos de Caixa, Banco e provedor de pagamentos;
3. totais recebidos/a receber/pagos/a pagar;
4. fluxo de caixa com entrada, saída e saldo acumulado;
5. vendas por dia.

Cada seção temporal possui período próprio.

## Transações

Filtros:

- contas a receber/pagar;
- vencimento/disponibilidade, competência ou pagamento;
- conta financeira;
- estado Bloqueado, Disponível, Em aberto, Atrasado ou Pago;
- forma de pagamento;
- categoria/plano de conta.

Tabela: data, titular/descrição, origem, forma, categoria, bruto, líquido/conta, status, pago e ações.

## Caixa

Histórico observado em estado vazio com colunas de ciclo completo: número, abertura, fechamento, datas, saldo inicial, saldo conferido e anotação.

Proposta: abertura e fechamento são operações auditáveis; divergência precisa de justificativa e nunca altera retroativamente os movimentos.

## Fiscal e comissões

Fiscal expõe famílias NFS-e, NF-e e NFC-e, download XML e configurações. Comissões expõe detalhadas, resumidas, pagas e configurações. Ambas estavam bloqueadas por entitlement, portanto regras internas são Unknown.

## Tratamento independente proposto

- separar saldo contábil, saldo disponível e saldo do provedor;
- tornar visível qual data governa cada visão;
- preservar referência de origem até venda/agendamento/ajuste;
- não usar switch simples para liquidar sem revisão, permissão e confirmação;
- mostrar bruto, taxas, líquido e conta de destino;
- caixa com contagem esperada × conferida e divergência;
- fiscal e comissão assíncronos, com status, erros recuperáveis e histórico;
- paywall deve explicar benefício sem ocultar se o usuário tem autorização versus plano insuficiente.
