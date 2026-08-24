# Roteiro de implementação — analytics e relatórios

## Princípio

Recriar a capacidade decisória, não números ou aparência do produto observado. Toda métrica precisa de definição testável, linhagem até os fatos de origem e política temporal explícita.

## Fase 1 — contratos de métricas

- catálogo de métricas com ID estável, nome, descrição, fórmula, granularidade, dimensão, unidade, timezone e versão;
- eventos/fatos mínimos: agendamento, mudança de status, atendimento, comanda, item vendido, pagamento, estorno, movimento financeiro, consumo/movimento de estoque e mensagem;
- dicionário de dimensões: unidade, profissional, cliente pseudonimizado, serviço, produto, categoria, canal e origem;
- testes com fixtures sintéticas cobrindo cancelamentos, estornos, reabertura e virada de período.

## Fase 2 — camada de leitura

- projeções incrementais por tenant e data;
- jobs idempotentes com checkpoint e reprocessamento;
- tabelas agregadas para dashboard e tabelas detalhadas para drill-down;
- cache por fingerprint de filtros com `calculado_em`;
- reconciliação diária entre fatos, agregados e razão financeiro.

## Fase 3 — painel MVP

1. filtro global de período/unidade/profissional;
2. KPIs com comparação anterior;
3. tendência de agendamentos e vendas;
4. status, ticket médio e desempenho profissional;
5. ocupação e mapa de calor;
6. drill-down para listas explicativas;
7. estados acessíveis de loading, vazio, erro e parcial.

## Fase 4 — motor de relatórios

- registro tipado de definições e filtros;
- executor síncrono/assíncrono;
- paginação e totais reconciliáveis;
- favoritos por usuário;
- exportação assíncrona com RBAC, auditoria, expiração e limite de PII;
- biblioteca inicial priorizada: financeiro, agendamentos, vendas e clientes.

## Fase 5 — metas

- definição versionada da métrica-alvo;
- metas por profissional/equipe/unidade;
- progresso derivado e snapshot periódico;
- alertas internos configuráveis;
- histórico imutável de alterações;
- entitlement desacoplado da UI.

## Critérios de pronto

- a mesma combinação de filtros retorna o mesmo total no KPI, gráfico, detalhe e exportação;
- estornos e cancelamentos aparecem conforme política documentada;
- nenhuma consulta cruza tenant;
- nenhuma exportação é produzida sem autorização e auditoria;
- gráficos são utilizáveis sem mouse, sem cor e via leitor de tela;
- p95 do dashboard dentro do orçamento definido com cache frio e quente;
- reprocessar um período não duplica fatos nem altera históricos fechados sem ajuste auditável.
