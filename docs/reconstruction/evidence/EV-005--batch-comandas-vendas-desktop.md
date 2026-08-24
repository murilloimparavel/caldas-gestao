# EV-005 — Comandas, vendas e pagamentos desktop

- **Data:** 24/08/2026
- **Rota:** `/sales`
- **Papel:** desconhecido
- **Viewport:** desktop 1710×929
- **Mutação:** nenhuma
- **Redação:** clientes, telefones, tickets, valores e profissionais reais não foram preservados

## Interações

- execução da busca com filtros padrão;
- inspeção dos filtros e da tabela populada;
- abertura de uma comanda finalizada existente;
- abertura do menu Outros;
- tentativa única de Ver pagamentos, que fechou o drawer sem apresentar conteúdo adicional;
- abertura de Nova comanda e cancelamento sem preencher.

## Descobertas

- a lista possui Ticket, Data, Cliente, Status, Valor, Pagamento e Nota Fiscal;
- filtros distinguem excluída/não excluída, finalizada/pendente, estado do pagamento e forma de pagamento;
- estados de cobrança visíveis: Bloqueado, Disponível, Em aberto, Atrasado e Pago;
- formas visíveis incluem meios internos, dinheiro, Pix, cartões, boleto, cheque, convênio e depósito;
- a amostra continha comandas finalizadas pagas, bloqueadas e de valor zero;
- a comanda existente abre em modo leitura com cliente, itens, profissional, quantidade, preço, desconto, totais, crédito, cashback, observações e contexto 360° do cliente;
- ações existentes: editar, excluir, ver pagamentos, imprimir, impressão térmica e histórico;
- Nova comanda oferece Salvar e Faturar; Faturar iniciou desabilitado.

## Segurança

- nenhum campo foi preenchido ou alterado;
- nenhuma comanda foi salva, faturada, editada ou excluída;
- nenhum pagamento, impressão, nota, conversa ou histórico foi acionado;
- nenhuma seleção em massa foi feita.

## Limitações

- detalhes de pagamento não abriram na tentativa observada e não foram repetidos às cegas;
- ações finais e validações não podem ser testadas na conta operacional;
- significado dos ícones sem nome e dos pagamentos bloqueados permanece desconhecido.
