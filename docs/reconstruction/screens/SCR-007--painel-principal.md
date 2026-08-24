# SCR-007 — Painel principal

## Rota e propósito

- rota observada: `/wow`;
- propósito: leitura operacional rápida, comparação temporal e detecção de gargalos;
- período padrão observado: intervalo recente com comparação contra o período anterior.

## Hierarquia visual

1. header com saudação, Filtrar e Atualizar;
2. seletor de período;
3. KPIs de vendas, agendamentos e comandas;
4. análise alternável entre Agendamentos e Comandas;
5. análises econômicas e por profissional;
6. distribuição, funil, ocupação e mapa de calor.

## Visualizações observadas

| Bloco | Forma visual | Leitura pretendida |
|---|---|---|
| Vendas totais | KPI + valor do dia + delta | volume financeiro e direção |
| Agendamentos | KPI + sparkline + crescimento | demanda no período |
| Comandas | KPI + sparkline + conversão | realização comercial |
| Tendência de visitas | barras por data | sazonalidade diária |
| Agendamentos por status | rosca + legenda | composição de status |
| Ticket médio | valor + comparação | qualidade da receita |
| Atendimentos por profissional | resumo + tabela ordenada | produtividade e valor médio |
| Vendas por categoria | distribuição | composição da receita |
| Funil de agendamentos | funil | perdas entre etapas |
| Ocupação da agenda | ranking + percentual + faixa textual | capacidade por profissional |
| Mapa de calor | hora × dia da semana | concentração de demanda |

## Comportamento observado

- Filtrar alterna a visibilidade/ênfase do painel de período sem aplicar automaticamente uma nova seleção.
- Atualizar é uma ação explícita; não foi acionada.
- A aba Agendamentos exibe tendência e distribuição por status.
- A aba Comandas substitui esse bloco por Vendas por dia; os blocos inferiores permanecem.
- Deltas usam cor, seta e texto; ocupação usa medalha, percentual e faixa qualitativa.

## Requisitos propostos para o produto novo

- cada KPI declara fórmula, unidade, timezone, período atual e período comparado;
- valores incompletos diferenciam zero real, ausência de dados e carregamento;
- clicar em um bloco leva ao relatório filtrado que explica o número;
- filtros ficam serializados na URL e podem ser compartilhados conforme autorização;
- todos os gráficos têm resumo textual, tabela alternativa e descrição acessível;
- cor nunca é o único canal para direção, status ou intensidade;
- heatmap oferece escala, legenda, tooltip por teclado e modo tabular;
- cache analítico exibe `calculado_em` e permite atualização idempotente;
- métricas financeiras respeitam fechamento, estorno e competência/caixa explicitamente.

## Desconhecidos

- fórmulas exatas de conversão, ocupação e etapas do funil;
- definição de venda e tratamento de cancelamento/estorno;
- timezone e corte diário;
- comportamento com múltiplas unidades e profissionais;
- drill-down, tooltips e estados mobile.
