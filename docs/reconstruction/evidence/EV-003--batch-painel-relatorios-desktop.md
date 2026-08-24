# EV-003 — Painel, relatórios e metas desktop

- **Data:** 24/08/2026
- **Ambiente:** aplicação autenticada com aparência de conta operacional
- **Papel:** desconhecido
- **Viewport:** desktop 1710×929
- **Mutação:** nenhuma
- **Redação:** nomes, valores, contagens e resultados reais não foram preservados

## Superfícies observadas

- painel principal em `/wow`;
- catálogo de relatórios em `/reports/*`;
- relatório financeiro representativo em `/reports/financial/dre`;
- ranking e mensagens em suas páginas iniciais;
- metas em `/goals`, limitada por entitlement da conta.

## Interações somente leitura

- abertura e fechamento do filtro do painel;
- alternância entre as abas Agendamentos e Comandas;
- navegação por todas as famílias de relatório;
- abertura de um relatório financeiro sem gerar o resultado;
- abertura do menu de exportação sem executar exportação;
- abertura de Metas e fechamento do paywall sem contratar.

## Evidência visual e estrutural

O painel combina cartões KPI, sparklines, barras temporais, rosca, comparação de períodos, tabela de ranking, distribuição por categoria, funil, ocupação e mapa de calor. Os gráficos são SVG renderizados por Recharts. Os elementos gráficos observados não possuíam `role` ou `aria-label` próprios.

O catálogo de relatórios é dividido em Favoritos, Financeiro, Agendamentos, Clientes, Vendas, Estoque, Notas Fiscais, Ranking e Mensagens. Relatórios detalhados usam filtros e geração sob demanda; exportação é uma ação separada.

## Resultado de segurança

- nenhum período, filtro, checkbox ou switch foi alterado;
- nenhum relatório foi gerado ou exportado;
- nenhum favorito foi alterado;
- nenhum item foi criado, editado, excluído ou contratado;
- nenhuma captura com dados operacionais foi persistida no repositório.

## Limitações

- fórmulas foram inferidas somente pelos rótulos visíveis, não validadas contra dados brutos;
- tooltips e comportamento responsivo não foram cobertos;
- a conta não possui acesso efetivo a Metas;
- estados de carregamento apareceram em Ranking/Mensagens, mas erros e timeouts não foram induzidos.
