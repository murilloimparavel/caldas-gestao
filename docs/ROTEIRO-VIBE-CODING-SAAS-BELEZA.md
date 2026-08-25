# Roteiro para recriar o produto com vibe coding

> **Resumo não canônico.** O backlog e as propostas atuais vivem em [`reconstruction/README.md`](reconstruction/README.md) e [`reconstruction/reconstruction-backlog.md`](reconstruction/reconstruction-backlog.md). Este roteiro deve ser revisado por marco, não usado como fonte de novos claims.

## Princípio

Vibe coding funciona melhor quando o agente recebe fatias verticais pequenas, contratos explícitos e testes verificáveis. O objetivo não é pedir “faça um Belasis”, mas construir um sistema próprio, módulo por módulo, com contexto versionado.

## Arquitetura recomendada

Para este SaaS transacional, não usar o site Astro estático como backend do aplicativo. Manter o marketing em Astro e criar o app como projeto separado.

Sugestão pragmática:

- web app: Next.js ou React + Vite;
- API: TypeScript com Fastify/NestJS ou backend integrado do framework;
- banco: PostgreSQL;
- ORM: Drizzle ou Prisma;
- autenticação: solução madura com MFA e sessões revogáveis;
- filas: Redis + worker;
- arquivos: storage S3 compatível;
- pagamentos: gateway brasileiro com PIX/cartão e webhooks;
- observabilidade: logs estruturados, tracing, erros e auditoria;
- testes: Vitest, Testing Library e Playwright;
- monorepo: pnpm + Turborepo, se houver app, API e workers separados.

## Fases

### Fase 0 — Descoberta e decisões

- concluir o dossiê por módulo;
- entrevistar 5–10 usuários;
- definir ICP e diferencial;
- escolher o MVP e registrar o que fica fora;
- definir LGPD, retenção, consentimento e trilha de auditoria;
- produzir modelo de domínio e mapa de eventos.

Saída: PRD, ADRs, glossário, ERD e backlog priorizado.

### Fase 1 — Fundação

- monorepo, CI e ambientes;
- design system próprio;
- tenant, unidade, usuários, papéis e permissões;
- autenticação, recuperação e MFA;
- auditoria e observabilidade;
- seeds e fábrica de dados sintéticos.

Critério de saída: isolamento multi-tenant e RBAC testados automaticamente.

### Fase 2 — Catálogo e pessoas

- clientes;
- profissionais e disponibilidade;
- serviços, produtos, categorias e preços;
- fornecedores;
- importação controlada e deduplicação.

Critério de saída: CRUD completo, busca, filtros, paginação e auditoria.

### Fase 3 — Agenda MVP

- visão diária/semanal;
- criação e edição;
- conflitos e bloqueios;
- status e cancelamento;
- confirmação e lembrete;
- check-in;
- link público de agendamento.

Critério de saída: fluxo E2E cliente → agenda → confirmação sem dupla reserva.

### Fase 4 — Atendimento e comanda (Concluída ✅)

- [x] Categorias de comanda e modelos (`SaleCategory`, `Sale`, `SaleItem`, `SaleStatusHistory`, `AppointmentSaleLink`);
- [x] Ações de ciclo de vida (`OpenSale`, `AddSaleItem`, `RemoveSaleItem`, `ApplySaleDiscount`, `TransitionSaleStatus`);
- [x] Frontend operacional completo de comandas (`sales/index.tsx`, `sales/show.tsx`, `sale-categories/index.tsx`, `calendar/index.tsx`);
- [x] Fechamento consolidado (`ClosingSession`, `FinalizeClosingSession`), recibo interno e histórico auditável;
- [x] Invariantes de totais, fechamento uniforme, concorrência otimista (`lock_version`) e idempotência testadas com 100% de aprovação. Pagamentos, recebimentos, gateways, Pix/cartão e estornos ficam para a Fase 5.

### Fase 5 — Financeiro, comissões e estoque

- caixa e lançamentos;
- contas a pagar/receber;
- regras de comissão versionadas;
- fechamento e pagamento de comissão;
- movimentos de estoque por venda/consumo;
- compras e inventário.

Critério de saída: razão financeiro/estoque reconciliável e imutável por eventos de ajuste.

### Fase 6 — Pacotes, assinatura e retenção

- modelos de pacote;
- saldo e validade;
- assinatura recorrente;
- retentativas e inadimplência;
- cashback, avaliações e campanhas;
- WhatsApp via API oficial e consentimento.

### Fase 7 — Relatórios e escala

- painel operacional;
- metas;
- indicadores financeiros;
- exportações;
- performance, acessibilidade e mobile;
- backups, restore testado e plano de incidentes.

## Ordem de entrega sugerida

```text
Fundação → Clientes/Profissionais/Serviços → Agenda
→ Comanda/Pagamento → Comissão → Financeiro/Estoque
→ Pacotes/Assinaturas → Marketing → Relatórios avançados
```

## Backlog inicial em fatias verticais

1. Como recepcionista, cadastro um cliente e encontro duplicidades por telefone.
2. Como gestor, cadastro serviço, duração, preço e profissionais habilitados.
3. Como recepcionista, vejo disponibilidade e crio agendamento sem conflito.
4. Como profissional, inicio e concluo o atendimento.
5. Como caixa, converto o atendimento em comanda e recebo por múltiplos meios.
6. Como gestor, vejo a comissão gerada pela regra vigente na data do atendimento.
7. Como estoque, vejo a baixa dos produtos consumidos/vendidos.
8. Como cliente, recebo confirmação e consigo cancelar dentro da política.

## Template de prompt para cada fatia

```text
Implemente a história [ID e texto].

Contexto obrigatório:
- leia /docs/domain/[dominio].md;
- leia /docs/adr relevantes;
- respeite o isolamento por tenant e o RBAC;
- não altere contratos fora do escopo sem ADR.

Critérios de aceite:
- [Given/When/Then];
- [validações];
- [permissões];
- [efeitos financeiros/estoque];
- [auditoria];
- [acessibilidade e responsividade].

Entregue:
- migração/schema;
- regra de domínio;
- API;
- UI;
- testes unitários, integração e E2E;
- documentação curta;
- evidências de check, build e testes.
```

## Guardrails para agentes de código

- Uma história por vez e commits pequenos.
- Nenhuma regra financeira apenas no frontend.
- Dinheiro em inteiro de centavos ou decimal exato, nunca float.
- Datas armazenadas com timezone e política explícita da unidade.
- Toda mutação crítica com idempotency key e auditoria.
- Webhooks assinados, idempotentes e reprocessáveis.
- RBAC validado no servidor.
- Toda query com escopo de tenant.
- Dados sintéticos em desenvolvimento e testes.
- Migrações reversíveis quando possível.
- Não aceitar implementação sem teste do happy path e dos principais erros.
- Não registrar PII, tokens ou payloads sensíveis em logs.

## Definition of Done

- critérios de aceite automatizados;
- revisão de segurança e LGPD proporcional ao risco;
- estados loading/empty/error definidos;
- desktop e mobile verificados;
- acessibilidade básica por teclado e leitor de tela;
- telemetria e auditoria adicionadas;
- documentação e changelog atualizados;
- build, typecheck, lint e testes verdes;
- rollback conhecido.

## Estratégia de paridade

Não buscar 100% de paridade desde o início. Classificar cada capacidade observada:

- **Essencial:** bloqueia a operação diária;
- **Importante:** reduz trabalho manual ou risco;
- **Diferencial:** melhora aquisição/retenção;
- **Posterior:** baixa frequência ou alta complexidade.

O MVP recomendado termina após agenda + comanda + fechamento + comissão básica. Registro de recebimentos, pagamentos, financeiro completo, assinatura, automações e relatórios avançados entram depois que o núcleo estiver validado.
