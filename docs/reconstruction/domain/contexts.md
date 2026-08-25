# Contextos delimitados propostos

> Este é um modelo original para o novo produto, não descrição do banco interno observado.

As classificações abaixo distinguem entidades observadas (`Observed`), interpretações (`Inferred`) e desenho original (`Proposed`). A fundação de plataforma é detalhada em [database-architecture.md](../../architecture/database-architecture.md).

## Plataforma, tenancy e acesso (F2)

- donos: `Tenant`, `Unit`, `User`, `Membership`, `Role`, `Permission` e `Entitlement`;
- `User` é identidade global; `Membership` concede acesso a um tenant; `membership_units` limita unidades e guarda `is_primary`; membership revogada pode voltar a `invited` por reinvite auditado;
- `Professional` não pertence a este contexto de autenticação: pode existir sem login e um usuário pode não ser profissional;
- auditoria, idempotência e entrega (`audit_events`, `idempotency_keys`, `outbox_events`, `inbox_events`) são infraestrutura de confiança, não tabelas de domínio operacional;
- mutações de negócio são tenant-scoped e revalidadas por Policy; operações explícitas de plataforma têm fluxo, role e auditoria próprios; `scope_kind`/`assignment_scope` persistem o alcance de papéis; entitlement não substitui autorização;
- **Classificação:** `Proposed`, apoiada por DB-001 a DB-009 e pela distinção observada entre profissional e usuário (UX-013).

## CRM

- dono: Cliente;
- responsabilidades: identidade, contatos, endereços, tags, relacionamentos e preferências;
- não possui: vendas, débitos, mensagens ou anamnese como colunas internas.

## Workforce e acesso

- donos: Profissional, Usuário, Papel e Permissão;
- políticas: capacidade de agenda, exposição online, comissão, estoque e vínculo de parceria;
- identidade autenticável é separada do cadastro profissional.

## Catálogo

- donos: Serviço, Produto, Categoria, Marca e Modelo de Pacote;
- políticas: preço vigente, duração, visibilidade, favoritos, fiscalidade e personalização por profissional.

## Estoque

- donos: ItemEstoque, Movimento, Lote, Validade e Solicitação;
- recebe eventos de compra, venda, consumo de serviço e ajuste;
- saldo é projeção derivada dos movimentos.

## Relacionamento e prontuário

- donos separados: Mensagem, Anotação, Arquivo, Anamnese e Consentimento;
- autorização e retenção reforçadas por LGPD.

## Financeiro/comercial

- donos: Venda/Comanda, Débito, Crédito, Cashback e Comissão;
- cliente e itens do catálogo são referências, não agregados internos.

## Ownership e dependências

| Contexto          | Fonte de verdade                                | Consumidores                     |
| ----------------- | ----------------------------------------------- | -------------------------------- |
| Plataforma/acesso | tenants, units, memberships, RBAC, entitlements | todos os contextos               |
| Auditoria/entrega | audit/outbox/inbox/idempotência                 | suporte, integrações e projeções |
| CRM               | cliente e contatos                              | agenda, venda, relacionamento    |
| Workforce         | profissional e vínculos                         | agenda, catálogo, comissão       |
| Financeiro        | obrigações e movimentos imutáveis               | caixa, relatórios, comissão      |

Nenhum contexto grava diretamente na tabela de outro. Foreign keys permitem referência; comandos cruzados passam por Action/serviço e eventos publicados após commit. A criação de entidades dependentes após uma raiz persistida é uma proposta derivada de RULE-009/010, não prova da implementação observada.

## Validação e ADRs pendentes

- validar memberships tenant-wide versus unit-scoped com papéis de recepção/profissional/financeiro;
- testar se uma mesma identidade pode alternar tenants e se cache de autorização é invalidado na revogação;
- ADR pendente para RLS, retenção/legal hold e papéis customizados;
- ADR pendente para Storage de arquivos sensíveis e eventual API pública.

## Vendas, categorias e checkout consolidado (Proposed — ADR-003)

O contexto de Vendas é dono de `sale_categories`, `sales`, `sale_items`, `sale_status_histories`, `appointment_sale_links` e das operações de abertura/fechamento. Uma comanda é independente de agendamento e cliente; ambos são opcionais conforme a categoria. No MVP, `Appointment 0..N Sale` e `Sale 0..1 Appointment`. `PaymentIntent`, `Payment`, `PaymentAllocation` e `Refund` pertencem ao subdomínio de cobrança e não fundem as comandas no checkout.

`SaleCategory` é unit-scoped no MVP (`tenant_id` + `unit_id`) e escolhe `uniqueness_scope` (`customer`, `appointment`, `reference`, `none`). O backend materializa `open_context_key` e PostgreSQL limita uma comanda ativa por categoria/contexto. O checkout consolidado seleciona várias comandas da mesma unidade/moeda e mesmo `checkout_subject`, cria pagamentos e alocações, e preserva identidade e histórico individual. Tenant-wide, entidade de mesa/referência e cliente anônimo ficam para decisões futuras.
