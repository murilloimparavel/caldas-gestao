# Roadmap do Caldas Gestão

Este é o documento único de planejamento do produto. PRDs, ADRs, documentos de domínio e especificações de sprint continuam sendo documentos de apoio; não são roadmaps alternativos.

## Estado atual

| Área | Estado | Observação |
| --- | --- | --- |
| Fundação, tenancy, RBAC e auditoria | Implementado | Base operacional e governança do SaaS |
| Catálogo e pessoas | Implementado | Clientes, profissionais, serviços, produtos, categorias e fornecedores |
| Agenda | Implementado | Disponibilidade, conflitos, estados e agendamento público |
| Atendimento e comandas | Implementado | Itens, descontos, transições, fechamento e recibo interno |
| Financeiro, caixa, comissões e estoque | Implementado | Fluxos operacionais e reconciliação documentados |
| Pacotes e assinaturas | Implementado no MVP interno | Snapshots históricos, saldo/ledger, expiração e reversão de pacotes; contratos, ciclos, franquias, consumo, renovação interna, pausa/cancelamento e situação operacional de assinaturas. Não processa cobrança externa. |
| Retenção comercial e campanhas internas | Implementado no MVP interno | Preferências por canal, inatividade, marcação/reativação, campanhas tenant/unidade, snapshot de audiência, fila interna idempotente e bloqueio por opt-out, legal hold ou anonimização. |
| Retenção legal | Implementado | Políticas por classe, legal hold, dry-run, anonimização irreversível de PII e auditoria/outbox. |
| Analytics e relatórios | Próximo marco | Dashboard existe; catálogo de métricas e motor de relatórios ainda precisam ser consolidados |
| Pagamentos externos e fiscal | Planejado | Gateways, PIX/cartão, estornos e emissão fiscal |
| Retenção, marketing e integrações avançadas | Parcial | Ainda faltam cashback/crédito, avaliações, provedores de comunicação, WhatsApp oficial, automações e métricas de campanha. |

## Fases de entrega

### Fase 0 — Descoberta e decisões

Consolidar PRD, ADRs, glossário, modelo de domínio, LGPD, retenção, auditoria e backlog. Manter decisões pendentes registradas antes de iniciar uma nova frente.

### Fase 1 — Fundação segura

Manter isolamento por tenant/unidade, usuários, papéis, permissões, autenticação, recuperação de conta, MFA, auditoria, observabilidade, seeds e dados sintéticos.

### Fase 2 — Catálogo e pessoas

Manter CRUD, busca, filtros, paginação, deduplicação e auditoria para clientes, profissionais, serviços, produtos, categorias e fornecedores.

### Fase 3 — Agenda MVP

Evoluir agenda diária/semanal, disponibilidade, bloqueios, conflitos, confirmação, lembretes, check-in e link público sem dupla reserva.

### Fase 4 — Atendimento e comanda — concluída

Comandas, itens, categorias, vínculo com agendamento, descontos, transições, fechamento consolidado, recibo interno, histórico auditável, concorrência otimista e idempotência.

### Fase 5 — Financeiro, comissões e estoque — concluída

Caixa (abertura, suprimento, sangria, fechamento e conferência), movimentos de estoque, regras e liquidação de comissões, contas a pagar/receber, fluxo de caixa e dashboard financeiro.

### Fase 6 — Pacotes, assinatura e retenção — núcleo MVP entregue; extensões em evolução

Entregue no núcleo interno:

- Pacotes com snapshot na venda, saldo, elegibilidade congelada, consumo concorrente, reversão, expiração agendada, auditoria, outbox e idempotência.
- Assinaturas com plano/contrato, ciclos, franquia e ledger de uso, renovação interna, limites, pausa, retomada, cancelamento e situação operacional sem gateway de pagamento.
- Retenção comercial com consentimento por canal, inatividade, marcação de risco, reativação e auditoria.
- Campanhas internas com estados `draft`/`active`/`paused`/`completed`, audiência idempotente por inatividade/status/consentimento, fila e processamento internos, retry seguro e revalidação antes da entrega.
- Retenção legal com política, legal hold, execução em dry-run e anonimização de PII preservando fatos e trilha auditável.

Ainda pendente nesta frente:

- Validar migrations, constraints compostas e concorrência no PostgreSQL/Supabase real.
- Operar as rotinas agendadas com monitoramento/alertas e métricas de fila.
- Cashback/crédito, avaliações, segmentação comportamental avançada, resultados de contato e métricas de conversão.
- Integrações com provedores de e-mail/SMS/WhatsApp, templates aprovados, webhooks e inbox; o status `sent` atual representa somente handoff interno, sem chamada a provedor externo.

### Fase 7 — Relatórios e escala — próximo marco

Entregar métricas versionadas, dashboard reconciliável, relatórios com drill-down e exportação, metas, performance, acessibilidade, mobile, backups e plano de incidentes.

## Prioridades

### P0 — Fundação operacional

1. Fechar lacunas de autorização, tenancy, auditoria e LGPD.
2. Consolidar pagamentos e ledger financeiro idempotente.
3. Garantir invariantes de comanda, estoque e caixa com testes.

### P1 — Operação completa

1. Validar e operar em PostgreSQL/Supabase as garantias de Pacotes, Assinaturas e Retenção.
2. Consolidar dashboard e relatórios reconciliáveis.
3. Adicionar provedores de notificação transacional e automações somente após decisão de canal/consentimento.

### P2 — Expansão

1. Fiscal assíncrono.
2. WhatsApp oficial, inbox e automações de marketing.
3. Metas, alertas, exportações e integrações avançadas.

## Próximo marco recomendado: analytics e relatórios

1. Definir catálogo de métricas, fórmulas, dimensões, granularidade e timezone.
2. Criar camada de leitura incremental por tenant e data, com checkpoint e reprocessamento.
3. Entregar painel com período/unidade/profissional, KPIs, tendências, ocupação e drill-down.
4. Criar motor de relatórios com filtros tipados, paginação, favoritos, exportação autorizada e auditoria.
5. Adicionar metas versionadas e alertas internos.

## Definition of Done

Cada fatia só é considerada pronta quando possui critérios de aceite automatizados, autorização no servidor, isolamento de tenant, estados loading/empty/error, acessibilidade básica, auditoria/telemetria sanitizada, documentação, build, typecheck, lint e testes verdes.

## Documentos de apoio

- [PRD de comandas e checkout](PRD--comandas-categorias-e-checkout.md)
- [Arquitetura do projeto](architecture/project-structure.md)
- [Plano frontend](reconstruction/frontend-implementation-plan.md)
- [Dossiê de reconstrução](reconstruction/README.md)
- [Documentos de sprint](architecture/)

Este arquivo é a fonte única para status e prioridade. Ao concluir uma fatia, atualize a tabela de estado, a prioridade correspondente e os documentos técnicos de apoio.
