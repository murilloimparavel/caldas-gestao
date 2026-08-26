# ADR-004 — RLS e contexto de tenant com Supabase Session Pooler

- **Status:** aceito
- **Data:** 26/08/2026
- **Decisores:** equipe do Caldas Gestão
- **Escopo:** PostgreSQL, Supabase Session Pooler, autorização de tenancy, Row Level Security (RLS) e role `caldas_runtime`

## Contexto

No Caldas Gestão, o isolamento multi-tenant e a integridade transacional são os pilares fundamentais de segurança e governança de dados (conforme estabelecido no [ADR-001](ADR-001--stack-inicial.md) e no [ADR-002](ADR-002--fundacao-de-dados-e-tenancy.md)).

Atualmente, o Laravel atua como a única autoridade de negócio, aplicando o isolamento por tenant no nível de aplicação através do `TenantContext`, `AuthorizationService`, Form Requests, Scopes Eloquent e Policies. No entanto, em cenários de alta concorrência e produção com banco gerenciado no Supabase, é fundamental estabelecer os padrões de arquitetura para:
1. Definir o papel da autoridade do Laravel em relação a uma eventual ativação de Row Level Security (RLS) no PostgreSQL.
2. Garantir a convivência segura e previsível entre o Supabase Session Pooler (pgBouncer/Transaction mode) e comandos de sessão/transação PostgreSQL (`SET LOCAL app.current_tenant_id`).
3. Delimitar os privilégios operacionais da role de banco de dados em runtime (`caldas_runtime`) frente à role administrativa/de migração.

## Decisões

### 1. Autoridade de Tenancy do Laravel e RLS como Defesa em Profundidade

- **Laravel como Autoridade Primária:** O Laravel é a autoridade central de autenticação, autorização (RBAC/ABAC) e contextos de tenancy (`TenantContext`). Todas as rotas, controllers, jobs e transações validam rigorosamente a qual tenant a requisição pertence.
- **RLS como Defesa em Profundidade:** A ativação de Row Level Security (RLS) no PostgreSQL é uma camada adicional (defense-in-depth), projetada para prevenir acidentes de vazamento de dados via SQL direto ou falha em queries brutas. A RLS **não substitui** o controle do Laravel nem transfere a regra de negócio para políticas de banco de dados.
- **Comando de Sessão Transacional:** Quando a RLS for ativada no futuro em `app.*`, o Laravel injetará o contexto do tenant na sessão da transação PostgreSQL emitindo o comando:
  ```sql
  SET LOCAL app.current_tenant_id = 'tenant-uuid-v7';
  ```
  O uso de `SET LOCAL` garante que a variável fique restrita exclusivamente à transação corrente (`BEGIN ... COMMIT/ROLLBACK`), evitando o vazamento do estado entre requisições na mesma conexão.

### 2. Comportamento do Supabase Session Pooler

- **Modo Transaction do Pooler:** O Supabase Session Pooler reutiliza conexões de banco de dados entre diferentes processos do Laravel (workers, PHP-FPM / Octane). Como as conexões são compartilhadas entre requisições de tenants distintos, **nenhum estado de sessão pode ser persistido fora de uma transação**.
- **Regras para Injeção de Contexto:**
  1. `SET LOCAL` deve ser obrigatoriamente chamado dentro de um bloco transacional (`DB::transaction`).
  2. Ao finalizar a transação (`COMMIT` ou `ROLLBACK`), o PostgreSQL limpa automaticamente a variável `app.current_tenant_id`.
  3. Consultas executadas fora de transação não assumirão um tenant implícito persistido na conexão reutilizada.
  4. Migrações, backups, DDL e rotinas administrativas utilizam uma conexão direta com o PostgreSQL (porta 5432), ignorando o Session Pooler.

### 3. Privilégios da Role `caldas_runtime`

- **Princípio do Menor Privilégio:** As requisições em tempo de execução da aplicação utilizam a role de banco de dados `caldas_runtime`.
- **Grants da `caldas_runtime`:**
  - Possui privilégios de leitura e escrita operacionais (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) apenas nas tabelas do schema `app`.
  - **Rejeição de DDL:** Não possui permissões de DDL (`CREATE`, `ALTER`, `DROP`, `TRUNCATE`).
  - **Proteção de Auditoria e Logs:** Não possui permissões de `UPDATE` ou `DELETE` na tabela `audit_events` (protegida por trigger e restrição de grants append-only).
  - **Schema Isolation:** Não possui acesso administrativo aos schemas de sistema do Supabase (`auth`, `storage`, `realtime`, `vault`).

## Consequências

### Positivas
- **Isolamento Blindado:** Garante dupla camada de segurança (Laravel + PostgreSQL RLS).
- **Compatibilidade com Pooling:** O uso de `SET LOCAL` dentro de transações evita contaminação de conexões no Session Pooler do Supabase.
- **Segurança de Runtime:** A role `caldas_runtime` não possui privilégios de alteração de schema nem de destruição de logs de auditoria.

### Custos e Riscos
- **Disciplina Transacional:** Mutações e consultas sujeitas a RLS devem ser executadas dentro do escopo transacional onde `SET LOCAL` foi configurado.
- **Overhead Mínimo:** A execução de `SET LOCAL` adiciona uma instrução SQL por bloco transacional.

## Referências

- [ADR-001 — Stack inicial do Caldas Gestão](ADR-001--stack-inicial.md)
- [ADR-002 — Fundação de dados e tenancy](ADR-002--fundacao-de-dados-e-tenancy.md)
- [ADR-003 — Categorias de comanda e fechamento consolidado](ADR-003--categorias-de-comanda-e-checkout-consolidado.md)
