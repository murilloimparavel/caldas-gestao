# ADR-002 — Fundação de dados e tenancy

- **Status:** proposta para aceitação antes da implementação da F2
- **Data:** 24/08/2026
- **Decisores:** equipe do Caldas Gestão
- **Escopo:** PostgreSQL, identidade, tenancy, autorização e evolução de migrations

## Contexto

O produto será um SaaS transacional para negócios de beleza, com agenda, clientes, catálogo, vendas, pagamentos, financeiro, comissões e módulos futuros. A evidência disponível descreve telas e comportamento observável, mas não contém o schema nem a API interna do produto estudado. Portanto, este ADR decide uma fundação independente para o novo produto.

Precisamos evitar três classes de falha antes da primeira tela operacional: acesso cruzado entre tenants, mistura de identidade com cadastro profissional e migrations que tornam evolução/rollback impossível. Também precisamos conectar Inertia/React ao backend sem expor tabelas operacionais ou fazer o frontend depender de detalhes do PostgreSQL.

Claims relacionados: `DB-001` a `DB-011` em [database-architecture.md](../architecture/database-architecture.md); `UX-013`, `BEH-010`, `BEH-016`, `BEH-022`; [roles-permissions.md](../reconstruction/roles-permissions.md); [network-observations.md](../reconstruction/network-observations.md).

## Decisões

### 1. Banco compartilhado + schema `app`

Adotamos um banco PostgreSQL compartilhado e um schema de aplicação dedicado, `app`. Tabelas de negócio novas não serão criadas em `public`. Schemas gerenciados do Supabase (`auth`, `storage`, `realtime`, `vault` e equivalentes) ficam fora do ownership das migrations Laravel e não recebem FKs de domínio.

O Supabase Data API fica desligado para o projeto, ou sem exposição do schema/tabelas operacionais. O navegador não recebe service key. Laravel é a autoridade de escrita, transação e autorização. Antes do primeiro `migrate` remoto, um provision command/deploy SQL pela conexão direta cria `app`, grants mínimos e `search_path=app,public`; `DB_SCHEMA=app`, `DB_SSLMODE=require`, DSN direto para migration e Session Pooler para runtime ficam definidos no ambiente.

As tabelas Laravel de infraestrutura (`migrations`, `sessions`, `cache`, `jobs`, `failed_jobs`) ficam em `app` para consistência, com IDs técnicos internos. PostgreSQL CI é o gate; SQLite permanece apenas fast loop.

**Consequências:** menor custo e operação simples, com necessidade de disciplina rigorosa em `tenant_id`, grants e scopes. Isolamento por schema/banco pode ser avaliado quando volume, compliance ou clientes dedicados justificarem o custo.

### 2. Tenancy por coluna obrigatória

Toda tabela tenant-owned terá `tenant_id NOT NULL`. Fatos de unidade terão também `unit_id NOT NULL`; registros tenant-wide podem não ter unidade. Índices e constraints de unicidade começam por `tenant_id`. Relações entre tenant, unidade, memberships e pivôs usam FKs compostas ou validação equivalente para impedir referências cross-tenant.

`TenantContext` no Laravel resolve o tenant a partir da sessão/membership. Input do cliente nunca escolhe autorização. Scopes, Policies, Form Requests, Actions, jobs e cache keys carregam o contexto; RLS não é a primeira linha de defesa.

**Consequências:** consultas exigem contexto explícito e testes negativos. A coluna permite relatórios e operação compartilhada com custo previsível; exige que nenhum novo agregado esqueça o escopo.

### 3. Usuários globais + memberships

`User` é identidade autenticável global. `Membership` liga user a tenant e controla status; `membership_units` restringe unidades e guarda a unidade primária. Um usuário pode pertencer a vários tenants. `Professional` será uma entidade do contexto Workforce e não é sinônimo de `User`: profissional pode não ter login e usuário pode não prestar atendimento. Membership revogada pode voltar a `invited` por comando de reinvite auditado; a linha é reutilizável e o histórico de atribuições permanece.

A ativação só ocorre pela `ActivateMembership` Action transacional: bloqueia membership e unidades ativas do tenant em ordem determinística, valida uma `membership_unit` ativa ou um papel tenant-wide ativo, incrementa `lock_version`, audita e publica após commit. Update direto de status é proibido no fluxo app; testes concorrentes são gate F2. Trigger de banco pode endurecer depois.

Papéis são tenant-scoped e permissões formam catálogo estável. `membership_roles` pode ser tenant-wide ou unit-scoped. Entitlements de plano/capacidade são avaliados separadamente de RBAC.

As Actions mutáveis adotam a ordem canônica de locks `tenant → membership → unit → role → pivôs`, em ordem crescente de UUID dentro de cada conjunto, e executam com retry transacional. Nesta fatia, a proteção de `roles.is_system` vale no caminho da aplicação (Policies, Actions e eventos Eloquent); escrita direta por `DB::table` ou SQL é deliberadamente fora dessa garantia. Antes de produção que exponha mutação de papéis, a próxima fatia de auditoria deve instalar um guard PostgreSQL/auditar esse bypass e restringir os grants da role runtime; não se deve alegar que uma proteção de modelo substitui essa barreira de banco.

`membership_roles` persiste `scope_kind` (`tenant`/`unit`), `assignment_scope`, `unit_id`, `lock_version` e `revoked_at`. A unicidade vale somente para assignments ativos; revogar preserva histórico. RBAC responde capacidade, enquanto ABAC valida ownership, unidade, estado, entitlement e step-up.

Entitlements persistem `trial`, `active`, `grace`, `suspended`, `expired` e `revoked`, com `starts_at` obrigatório, `ends_at > starts_at` quando presente e `quantity >= 0` quando presente. F2 permite uma concessão vigente por chave; agenda de versões futuras exige ADR posterior.

**Consequências:** convite, revogação, reinvite, troca de unidade e auditoria ficam explícitos. Evitamos duplicar credenciais ou dar acesso por uma coluna `role` em users. O onboarding precisa criar/seedar papéis por tenant de forma idempotente; conflitos de assignment usam `lock_version`.

### 4. UUIDv7 para users e agregados

Users e agregados de domínio usam UUIDv7 gerados na aplicação e armazenados como `uuid`; IDs aparecem como strings nos contratos frontend. A baseline física preserva `email` e `password` para Laravel/Fortify e adiciona `email_normalized` para unicidade/busca. Antes do primeiro migrate remoto, `User` recebe `HasUuids`, `passkeys.user_id` e `sessions.user_id` tornam-se UUID, e factories, reset tokens, 2FA e testes são coordenados. Tabelas internas de infraestrutura que não são recursos públicos podem continuar com `bigint` ou string técnica, como jobs, migrations e o ID físico de outbox/audit.

**Consequências:** IDs são opacos e seguros para API, ordenáveis por tempo e compatíveis com geração offline/na borda. UUIDv7 precisa ser gerado/testado de forma consistente; uma extensão PostgreSQL só será adicionada por ADR próprio.

### 5. Idempotência, outbox/inbox e after-commit

Comandos F2 são sempre tenant-owned: `idempotency_keys.tenant_id` é obrigatório, `actor_user_id` pode ser nulo, e a unicidade usa `CREATE UNIQUE INDEX ... NULLS NOT DISTINCT` em PostgreSQL 15+, emitido com `DB::statement` na migration Laravel, ou dois índices parciais equivalentes. Operações de plataforma sem tenant ficam fora desta tabela até ADR próprio.

`outbox_events` mantém status `pending`, `available`, `publishing`, `retryable`, `dead` e `published`, além de `correlation_id`, `causation_id`, `actor_user_id`, `aggregate_version`, `last_attempt_at`, `dead_at`, `locked_at`, `locked_by` e `lease_until`. Claim é atômico; reaper recupera `publishing` com lease expirado para `retryable`. A outbox é gravada na mesma transação do agregado; publicação só ocorre após commit. Inbox usa estados equivalentes, incluindo `retryable`, leases e dead-letter.

**Consequências:** retry é observável e deduplicável, mas a configuração Laravel `after_commit` precisa ser verdadeira ou todo dispatch deve usar `dispatchAfterCommit`. O gate de aprovação exige teste de rollback que prove que nenhum evento foi publicado antes do commit.

### 6. Session Pooler em runtime; conexão direta para migrations

Requests e workers Laravel usam Session Pooler com TLS (`sslmode=require`) quando o ambiente Supabase exigir controle de conexões. Migrations, schema dump, backup/restore e DDL administrativa usam conexão direta, também com TLS, em janela controlada. A configuração deve validar prepared statements, transações e comportamento de conexões reutilizadas.

**Consequências:** reduzimos pressão de conexões no runtime sem usar pooler para DDL sensível. São necessários dois DSNs/segredos operacionais e testes de paridade; pooler não pode virar uma segunda fonte de schema.

### 7. RLS adiada

RLS é uma defesa adicional possível, mas não será requisito da F2. Adoção futura exige mecanismo confiável para definir/limpar tenant context em cada transação, inclusive com Session Pooler e jobs, além de testes de conexão reutilizada e ausência de contexto. Laravel `TenantContext`, scopes e Policies continuam obrigatórios mesmo com RLS.

**Consequências:** implementação inicial é menos frágil e mais compatível com Laravel/queue. Assumimos risco residual até que o gate de RLS seja aprovado; o risco é mitigado por FKs, Policies e testes cross-tenant.

### 8. Auditoria append-only e produção com PII

`audit_events` permanece append-only e registra tenant/unidade, ator, ação, recurso, motivo e correlação, com payload mínimo. O mecanismo escolhido é o trigger PostgreSQL `audit_events_append_only_guard`, criado por migration/provisionamento e owned pela role de migration, com função que rejeita `UPDATE`/`DELETE`. A role de runtime recebe somente grants necessários, sem `UPDATE`/`DELETE` nem bypass; o provisionamento Supabase usa roles disponíveis no projeto e não presume superuser. Teste de grants e tentativa de alteração/apagamento é obrigatório.

Nenhum deploy de produção é aprovado sem PII production gate: secrets fora do repositório, logs/tracing/Redis/outbox sem PII desnecessária, criptografia, retenção, backup/restore, acesso mínimo e revisão LGPD. O shell pode usar fixtures antes da F2; rotas persistentes reais aguardam o bootstrap de tenancy e identidade.

### 9. Expand-contract, backfill e forward-fix

Mudanças de schema seguem expand → backfill idempotente → dual-read/write quando necessário → contract. Migrations avançam; correções de dados são comandos auditados, versionados e repetíveis, não rollback destrutivo. Índices/constraints grandes têm janela, timeout e observabilidade. Eventos carregam `event_version` e outbox é gravada na mesma transação do fato.

**Consequências:** deploys podem ser compatíveis entre versões e rollback de código permanece possível durante a janela expand. A equipe mantém colunas legadas por mais tempo e precisa de métricas/checksums para concluir o contract com segurança.

### Semântica estrutural de escopo por unidade

Uma permissão concedida por assignment `tenant` é aplicável a qualquer unidade ativa do tenant, mesmo quando a membership não possui uma linha em `membership_units`; o contexto continua exigindo membership ativa e tenant ativo. Uma permissão concedida por assignment `unit` só é aplicável quando `unit_id` coincide com a unidade consultada e existe vínculo ativo em `membership_units` para a mesma membership/tenant. Unidade de outro tenant, inativa ou não vinculada para um assignment unit-scoped nunca é autorizada. Policies de unidade avaliam o objeto da unidade solicitado e não aceitam uma unidade selecionada pelo caller como prova de acesso.

### Onboarding create versus resume

`OnboardTenant` é uma operação create-only: slug existente produz conflito e nunca admite ingresso ou reconciliação implícita. `ResumeTenantOnboarding` é a operação explícita de reconciliação, exigindo `TenantContext` revalidado, membership ativa e permissionamento de owner; ambas as operações usam transações com retry e lock order determinística. O catálogo de permissões do owner é provisionado por serviço idempotente, separado das migrations/DDL.

## O que entra na F2

`tenants`, `units`, `users`, `memberships`, `membership_units`, `roles`, `permissions`, `role_permissions`, `membership_roles`, `entitlements`, `audit_events`, `idempotency_keys`, `outbox_events` e `inbox_events`, no schema `app`.

A F2 não cria CRM, profissional, catálogo, agenda, venda, pagamentos, ledgers, fiscal, arquivos ou relatórios. Esses contextos entram em migrations posteriores, sempre preservando `tenant_id`, `unit_id`, IDs UUIDv7, auditoria e contratos de evento.

## Alternativas rejeitadas

### Um schema/banco por tenant

Oferece isolamento físico maior, mas aumenta migrations, provisioning, pooling, observabilidade e relatórios cross-tenant antes de existir necessidade medida.

### Supabase Auth + Laravel Auth simultâneos

Criaria duas fontes de identidade e estados de sessão. O primeiro ciclo usa autenticação Laravel/Fortify; eventual migração exige ADR e plano de identidade.

### Role única em `users`

Não representa multi-tenant, escopo por unidade, múltiplos papéis ou revogação auditável. Membership e pivôs mantêm essas dimensões explícitas.

### RLS como única segurança

RLS não substitui autorização de domínio, Policies, estado do recurso ou controle de entitlement, e seu contexto com pooler/jobs precisa ser demonstrado.

### Alterar/deletar histórico financeiro

Prejudica reconciliação, auditoria e LGPD accountability. Ledgers e audit são append-only; correções são ajustes/reversões.

## Gate de aceitação

Antes de marcar este ADR como aceito, devem existir:

- revisão do mapa de tabelas F2 e dos grants do schema `app`;
- prova em PostgreSQL real de FKs compostas cross-tenant e uniques com NULL;
- teste de dois usuários/tenants/unidades e revogação de membership;
- teste concorrente de `ActivateMembership` contra revogação e alteração de unidades/papéis;
- teste concorrente de idempotency e outbox/inbox;
- teste do trigger append-only e grants com role de runtime sem pressupor superuser;
- teste de Session Pooler para runtime e conexão direta para migration;
- plano de bootstrap sem apagar migrations scaffold ou dados existentes;
- decisão inicial de retenção LGPD e owner operacional.

## ADRs seguintes

- RLS e contexto transacional;
- retenção/anonymização/legal hold;
- região, PITR, restore e residência no Supabase;
- Storage e documentos sensíveis;
- particionamento de auditoria, eventos e ledgers;
- papéis customizados e versionamento de permissões;
- API pública/mobile e read models;
- geração/extensão UUIDv7.

## Referências

- [ADR-001 — Stack inicial](ADR-001--stack-inicial.md)
- [Arquitetura de dados](../architecture/database-architecture.md)
- [Modelo de domínio](../reconstruction/domain/model.md)
- [Proposta de banco](../reconstruction/domain/database-proposal.md)
- [Governança de dados](../reconstruction/domain/data-governance.md)
