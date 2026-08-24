# EV-006 — Financeiro, caixa, comissões e fiscal

- **Data:** 24/08/2026
- **Papel:** desconhecido
- **Viewport:** desktop 1710×929
- **Mutação:** nenhuma
- **Redação:** titulares, tickets, saldos, valores e descrições reais não foram preservados

## Rotas observadas

- `/finance/dashboard` — painel financeiro;
- `/finance/transactions` — transações;
- `/finance/cash-accounting/history` — histórico de caixa;
- `/invoices/invoice` — nota fiscal de serviço;
- `/finance/commissions` — comissões.

## Evidência estrutural

- painel: receber/pagar hoje, saldos por conta, totais, fluxo de caixa e vendas por dia;
- transações: contas a pagar/receber, três tipos de data, contas, estados, meios, categorias, bruto/líquido, origem e marcação Pago;
- transação originada por comanda mantém referência visível à venda;
- menu de lançamento observado ofereceu Excluir; ícone Editar não possuía controle semântico claro;
- histórico de caixa: número, responsáveis por abertura/fechamento, datas, saldo inicial/conferido e anotação; estado vazio observado;
- fiscal: NFS-e, NF-e, NFC-e, XML e Configurações, bloqueado por entitlement na conta;
- comissões: Detalhadas, Resumidas, Pagas e Configurações, período/profissional; acesso observado bloqueado por entitlement.

## Segurança

- não foram acionados Novo, Calcular totais, switches de pagamento, edição ou exclusão;
- nenhum caixa foi aberto/fechado;
- nenhum XML foi baixado e nenhuma nota foi emitida;
- nenhum pagamento de comissão ou contratação foi acionado.

## Limitações

- Caixas abertos não navegou a uma superfície distinta na tentativa observada;
- paywalls impediram aprofundar fiscal e comissões;
- não foram observadas transições reais, erros ou reconciliação.
