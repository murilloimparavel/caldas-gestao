# SCR-008 — Relatórios e metas

## Catálogo de relatórios

Rota inicial: `/reports/favorites`. Favoritos começa com estado vazio e orientação para favoritar relatórios pela estrela.

| Família | Relatórios observados |
|---|---|
| Financeiro | resultados, receita líquida de serviços/produtos, projeção, fluxo, recebimentos, despesas, extratos e histórico de caixa |
| Agendamentos | todos, excluídos, origem, criação e cuidados do dia |
| Clientes | aniversariantes, pendentes/inadimplentes e retornos do dia |
| Vendas | comandas/pacotes, produtos/serviços, extrato de pacotes, assinaturas, cashback e avaliações pendentes |
| Estoque | movimentação, lista, sugestão de compra e produtos consumidos |
| Notas fiscais | todas as notas |
| Ranking | vendas e indicação de clientes |
| Mensagens | enviadas e métricas por canal/finalidade |

## Relatório detalhado representativo

Em Resultados Financeiros foram observados:

- filtro de tratamento do produto consumido;
- período;
- conta e condição de conta ativa;
- seleção múltipla de planos de conta;
- ação Gerar relatório;
- menu de exportação para Excel;
- navegação lateral entre relatórios e ícone de favorito.

O resultado não foi gerado e a exportação não foi executada.

## Padrão proposto

- separar `ReportDefinition`, `ReportRun` e `ExportJob`;
- validar filtros no servidor e registrar versão da definição usada;
- execução curta síncrona e longa assíncrona, com progresso/cancelamento;
- exportação exige permissão adicional, trilha de auditoria e expiração;
- favoritos são preferência por usuário, nunca permissão de acesso;
- totais devem reconciliar com relatórios de detalhe e declarar política contábil;
- filtros reutilizáveis usam esquema tipado por relatório;
- resultados grandes usam paginação/cursor e limites de intervalo;
- estados explícitos: inicial, filtros inválidos, gerando, pronto, vazio, parcial, falhou e expirado.

## Metas

Rota observada: `/goals`. Estrutura visível:

- navegação mensal anterior/próximo;
- filtros por período e profissionais;
- tabela Profissional, Período, Progresso e Ações;
- CTAs Filtrar, Ações e Novo;
- estado vazio.

A conta abriu automaticamente um diálogo de funcionalidade não contratada. Nenhum CTA de contratação ou criação foi acionado.

### Modelo proposto de meta

- escopo: tenant, unidade, equipe ou profissional;
- métrica versionada e unidade explícita;
- período com timezone;
- alvo, baseline opcional e direção desejada;
- progresso derivado de fatos analíticos, nunca atualizado manualmente;
- estados rascunho, ativa, atingida, encerrada e cancelada;
- regras para sobreposição e alteração durante o período;
- entitlement validado no servidor.
