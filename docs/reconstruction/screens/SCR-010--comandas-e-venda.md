# SCR-010 — Comandas e venda

## Listagem

- busca por ticket, cliente, número ou valor;
- filtros por exclusão, período, status da comanda, estado e forma de pagamento;
- seleção em massa e paginação;
- colunas de identidade, data, cliente, status, valor, pagamento, nota fiscal e ações.

## Comanda existente

### Contexto do cliente

- contato e Conversar;
- aniversário, cashback, crédito;
- comandas e pagamentos em aberto;
- pacotes, assinaturas e anotações.

### Corpo da venda

- cliente, data e número da comanda;
- itens com descrição, profissional, quantidade, valor unitário, desconto e total;
- totais de desconto, crédito, cashback e venda;
- observações;
- modo leitura com entrada explícita em Editar comanda.

### Ações

- Outros: Imprimir, Impressão térmica, Histórico;
- Cancelar/fechar drawer;
- Editar comanda;
- Excluir;
- Ver pagamentos.

## Nova comanda

- cliente pesquisável;
- data e número opcional aparente;
- primeiro item vazio com profissional default;
- quantidade, preço, tipo/valor de desconto;
- crédito e cashback inicialmente indisponíveis sem cliente elegível;
- total derivado;
- Salvar e Faturar como comandos distintos.

## Inteligência UX proposta

- usar um checkout progressivo: contexto → itens → benefícios → cobrança → revisão;
- separar claramente salvar rascunho, iniciar cobrança e concluir venda;
- explicar por que Faturar está desabilitado;
- exibir origem de valor zero: pacote, assinatura, crédito, cortesia ou desconto;
- estado de pagamento não deve aparecer como se fosse status da venda;
- itens e totais precisam de cálculo determinístico no servidor;
- ações destrutivas fora do grupo de navegação/impressão;
- histórico sempre visível, com ator, momento, antes/depois e motivo;
- botões somente com ícone precisam de nomes acessíveis e tooltip.
