# ADR-005 — Padronização de UUIDv7 para tabelas operacionais e de eventos

- **Status:** aceito
- **Data:** 26/08/2026
- **Decisão:** Adotar UUIDv7 (time-ordered UUIDs) para chaves primárias e identificadores de tabelas operacionais, eventos, logs de auditoria e lançamentos financeiros

## Contexto

Em sistemas multi-tenant transacionais como o Caldas Gestão, a escolha do tipo e formato de chave primária tem impacto direto em segurança, performance de banco de dados e previsibilidade dos dados.

O uso de inteiros auto-incrementais (`BIGINT`) expõe a previsibilidade das IDs e o volume de transações para usuários externos (enumeração de recursos, adivinhação de URLs ou volumes de vendas de concorrentes). Além disso, dificulta a geração distribuída de IDs no frontend ou em clientes desacoplados antes da persistência.

Por outro lado, o uso de UUIDv4 tradicional (completamente aleatório) resolve o problema de segurança e geração descentralizada, porém degrada severamente a performance do índice B-Tree do PostgreSQL sob alto volume de escrita. Por ser totalmente aleatório, cada inserção em tabelas com UUIDv4 causa fragmentação de páginas do índice B-Tree, levando a *page splits* constantes, cache misses e degradação no desempenho de I/O de disco.

As tabelas de eventos, logs de auditoria, lançamentos financeiros e registros operacionais do Caldas Gestão possuem alto volume de gravações sequenciais no tempo e exigem ordenação natural eficiente.

## Decisão

Adotar a padronização de **UUIDv7 (RFC 9562)** como identificador único padrão para tabelas operacionais, financeiras, de auditoria e de eventos no banco de dados PostgreSQL.

### Princípios da implementação

1. **Estrutura do UUIDv7:**
   - O UUIDv7 combina um timestamp de milissegundos nos primeiros 48 bits com bits de aleatoriedade nos bits restantes.
   - Isso garante ordenação temporal natural por padrão (*time-ordered*).

2. **Benefícios no PostgreSQL:**
   - **Índices B-Tree eficientes:** Inserções ocorrem sempre no final do índice B-Tree (semelhante a um `BIGINT` auto-incremental), eliminando a fragmentação de páginas e reduzindo drasticamente o *write amplification* e *bloat*.
   - **Não-previsibilidade prática:** Os bits aleatórios inferiores continuam garantindo alta entropia e imprevisibilidade no tempo para atores externos, impedindo enumeração direta de recursos.
   - **Performance de varreduras:** Consultas por intervalo de tempo se beneficiam da localidade de referência no cache de dados e índices.

3. **Aplicação na camada de dados (Laravel & PostgreSQL):**
   - No Laravel 11+, utilizar `HasUuids` ou a geração nativa `Str::orderedUuid()` / `Str::uuid7()`.
   - Nas migrations do PostgreSQL, definir colunas do tipo `uuid` com valor default `gen_random_uuid()` (caso PostgreSQL 17+) ou geradas via aplicação/extension dedicada.
   - Aplicar a padronização obrigatoriamente nas tabelas de:
     - Eventos de domínio e auditoria (`domain_events`, `audit_logs`);
     - Lançamentos financeiros e comissões (`financial_transactions`, `commissions`);
     - Comandas e itens operacionais (`orders`, `order_items`, `appointments`).

## Alternativas consideradas

### Auto-increment (BIGINT)
Rejeitado devido aos riscos de enumeração de dados (ID harvesting), vazamento de métricas operacionais entre tenants e dificuldades para sincronização distribuída.

### UUIDv4 (Aleatório)
Rejeitado devido à degradação de performance nos índices B-Tree sob alta concorrência e volume de gravações, causando fragmentação prematura da base de dados no Supabase/PostgreSQL.

### ULID (Universally Unique Lexicographically Sortable Identifier)
Considerado, porém rejeitado em favor do UUIDv7 por este ser um padrão formal IETF (RFC 9562) nativamente suportado e representado como o tipo primitivo `uuid` de 128-bits no PostgreSQL e no ecossistema Laravel/PHP moderno.

## Consequências

### Positivas
- Preserva a alta performance de escrita no índice B-Tree do PostgreSQL igualando-se a IDs sequenciais.
- Mantém o isolamento contra enumeração maliciosa e adivinhação de chaves.
- Permite ordenação cronológica primária pela própria chave.
- Padrão nativo e interoperável em bibliotecas modernizadas.

### Custos e riscos
- Obras de migração exigem atenção ao garantir que colunas UUID estejam mapeadas corretamente nas migrations e Models (`protected $keyType = 'string'; public $incrementing = false;`).
- Exige atenção na extração de timestamps diretamente da chave caso a aplicação passe a confiar nisso, devendo preferir colunas explícitas `created_at` para regras de negócio.
