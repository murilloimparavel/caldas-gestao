# Caldas Gestão

Monólito modular para gestão operacional e financeira de negócios de beleza.
O projeto está no início do scaffold: os contextos de negócio já têm fronteiras
documentadas e a implementação evolui em fatias verticais. A separação de
domínios é, neste momento, principalmente arquitetural e documental; o código
executável ainda segue as convenções Laravel em `app/Actions`, `app/Http`,
`app/Models` e `database`.

## Stack atual

As versões abaixo refletem as restrições do `composer.json`/`package.json` e o
lockfile deste repositório:

- PHP `^8.3` (CI usa PHP 8.5) e Laravel `^13.17` (lock atual 13.26.1);
- React `^19.2` + TypeScript `^5.7` + Vite `^8.0`;
- Inertia Laravel/React `^3.0`;
- Tailwind CSS `^4.0`;
- PostgreSQL gerenciado pelo Supabase e Redis são os serviços previstos para
  produção, mas não são necessários para desenvolvimento ou CI;
- Pest `^5.1`, PHPStan/Larastan, ESLint, Prettier e TypeScript para qualidade;
- Vitest e Playwright permanecem previstos no ADR, mas ainda não fazem parte
  do scaffold atual.

## Requisitos

- PHP 8.3 ou superior, com a extensão PDO SQLite habilitada;
- Composer 2;
- Node.js 22 e npm;
- Git.

Não é necessário instalar PostgreSQL, Redis ou outro serviço externo para
iniciar o projeto localmente.

## Setup local

```bash
git clone <url-do-repositorio> caldas-gestao
cd caldas-gestao
cp .env.example .env
composer setup
```

`composer setup` instala as dependências PHP, gera `APP_KEY`, executa as
migrations em `database/database.sqlite`, instala as dependências JavaScript
com `npm ci` e gera o bundle de produção. O arquivo SQLite e o `.env` são
locais e não devem ser commitados.

Se for necessário executar as etapas separadamente:

```bash
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
```

## Desenvolvimento

O comando recomendado inicia os processos de desenvolvimento registrados pelo
Laravel:

```bash
composer dev
```

Alternativamente, os processos podem ser executados em terminais separados:

```bash
php artisan serve
npm run dev
```

## Testes e qualidade

Os comandos disponíveis são:

```bash
# Formatação PHP (altera arquivos)
composer lint

# Formatação PHP, PHPStan e testes Pest
composer test

# Checks frontend
npm run lint:check
npm run format:check
npm run types:check
npm run build

# Gate completo usado no CI
composer ci:check
```

O workflow em `.github/workflows/tests.yml` sobe PostgreSQL efêmero, usa uma
role runtime de privilégios mínimos e uma conexão administrativa separada para
migrations. Cache em memória, sessão em memória e fila síncrona mantêm o gate
reprodutível. Assim, um checkout limpo executa setup, qualidade, build
frontend, PHPStan e Pest sem depender de serviços persistentes externos.

## Configuração de produção

O `.env.example` usa defaults locais deliberadamente seguros para onboarding.
Em deployment, substitua-os por variáveis injetadas pelo secret store da
infraestrutura. Nunca coloque credenciais no `.env.example`, no README ou no
Git.

### Supabase PostgreSQL

Ative a conexão PostgreSQL com as variáveis abaixo (os valores são exemplos,
não credenciais):

```dotenv
DB_CONNECTION=pgsql
DB_HOST=your-project.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=caldas_runtime.your-project
DB_PASSWORD=<runtime-secret-do-deployment>
DB_SCHEMA=app
DB_SEARCH_PATH=app,public
DB_RUNTIME_ROLE=caldas_runtime
DB_PROVISION_STRICT=true
DB_SSLMODE=require

MIGRATION_DB_CONNECTION=pgsql
MIGRATION_DB_HOST=db.your-project.supabase.co
MIGRATION_DB_PORT=5432
MIGRATION_DB_DATABASE=postgres
MIGRATION_DB_USERNAME=postgres
MIGRATION_DB_PASSWORD=<migration-secret-do-deployment>
MIGRATION_DB_SSLMODE=require
```

As variáveis `DB_*` são exclusivas do runtime e apontam para o Session Pooler.
O username aceito pelo pooler pode incluir o sufixo do projeto, enquanto
`DB_RUNTIME_ROLE` contém o nome PostgreSQL efetivo, sem esse sufixo. As
variáveis `MIGRATION_DB_*` apontam para o endpoint direto e usam a identidade
administrativa somente durante provisionamento e migrations.

O pooler, o endpoint direto e a região devem ser escolhidos conforme a
infraestrutura do ambiente. Migrations continuam sendo executadas pelo
Laravel; o frontend não acessa as tabelas operacionais diretamente pela Data
API do Supabase. Consulte o
[ADR-001](docs/adr/ADR-001--stack-inicial.md) e o
[runbook de bootstrap](docs/operations/database-bootstrap.md) para os limites
e comandos dessa decisão.

### Redis

Redis é opcional no desenvolvimento e esperado para cache, filas, locks e
sessões em produção. Configure a URL ou os campos de conexão pelo secret
store, por exemplo:

```dotenv
REDIS_CLIENT=phpredis
REDIS_URL=<url-do-redis>
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Se a infraestrutura não fornecer uma URL, use `REDIS_HOST`, `REDIS_PORT`,
`REDIS_USERNAME` e `REDIS_PASSWORD` separadamente. O endpoint de health do
Redis retorna `503` quando o serviço não está disponível; isso é intencional e
não impede o processo web de iniciar.

## Health checks

Com a aplicação rodando em `http://localhost:8000`:

```bash
curl http://localhost:8000/health/app
curl http://localhost:8000/health/database
curl http://localhost:8000/health/redis
```

`/health/app` verifica apenas o processo web. `/health/database` verifica a
conexão configurada e `/health/redis` verifica Redis separadamente. Os dois
últimos retornam `503` com status `unhealthy` quando sua dependência falha.
Com os defaults locais, database fica disponível via SQLite e Redis só ficará
`ok` quando um Redis local estiver rodando.

## Estrutura modular

O monólito é organizado por fronteiras de negócio, mas ainda não por módulos
PHP autossuficientes. `app/Domain/*` contém os READMEs das fronteiras e deve
receber código somente quando houver responsabilidade real; não há classes PHP
nessas pastas hoje. A implementação atual é distribuída assim:

- `app/Actions`: casos de uso agrupados por fatia (`Identity`, `Customers`,
  `Calendar`, `Sales`, `Finance`, `Inventory`, `Marketing` etc.);
- `app/Http/Controllers`, `app/Http/Requests` e `routes`: adaptação HTTP,
  validação de entrada e rotas nomeadas;
- `app/Models`, `app/Enums` e `database`: persistência Eloquent, tipos de
  domínio e fonte de verdade do schema;
- `app/Policies`, `app/Rules`, `app/Contracts` e `app/Support`: autorização,
  regras reutilizáveis, contratos e suporte transversal;
- `app/Jobs` e `app/Console/Commands`: processamento assíncrono e operações
  agendadas/administrativas;
- `resources/js/pages`: entradas Inertia por fluxo;
  `resources/js/features`: comportamento específico; `components`, `layouts`,
  `hooks`, `lib` e `types`: UI e infraestrutura frontend compartilhadas;
- `resources/js/actions` e `resources/js/routes`: funções TypeScript geradas
  pelo Wayfinder para chamar controllers e rotas Laravel.

### Fronteiras de negócio

Os contextos documentados são:

| Contexto | Responsabilidade |
| --- | --- |
| `Identity` | identidade, tenant, unidade e autorização |
| `Customers` | clientes, busca, perfil e histórico |
| `Catalog` | serviços, produtos, categorias e fornecedores |
| `Calendar` | disponibilidade, agenda, recorrência e conflitos |
| `Orders` | comandas, checkout, pagamentos e auditoria de alterações |
| `Finance` | caixa, transações, conciliação e comissões |
| `Analytics` | consultas, indicadores e relatórios |

Cada contexto possui um README em `app/Domain/<Contexto>/README.md`. O mapa
mais detalhado de ownership, dependências e decisões propostas está em
[`docs/reconstruction/domain/contexts.md`](docs/reconstruction/domain/contexts.md).

### Regras de dependência

- `app/Http` adapta requisições e não deve concentrar regra de negócio;
- Actions são a entrada preferencial para mutações e orquestração de casos de
  uso;
- Policies revalidam autorização no backend; entitlement não substitui Policy;
- contextos não devem gravar diretamente em tabelas pertencentes a outro
  contexto; referências são permitidas, mas operações cruzadas devem passar por
  Action/serviço e, quando aplicável, eventos após commit;
- modelos Eloquent são compartilhados nesta fase. A extração para módulos
  PHP completos é uma evolução futura, não uma característica já concluída.

Essas regras são intenção arquitetural e devem ser confirmadas/atualizadas à
medida que novas fatias forem implementadas. A documentação de estrutura em
[`docs/architecture/project-structure.md`](docs/architecture/project-structure.md)
é a referência complementar.

## Decisões e plano

- [ADR-001 — Stack inicial](docs/adr/ADR-001--stack-inicial.md);
- [ADR-002 — Fundação de dados e tenancy](docs/adr/ADR-002--fundacao-de-dados-e-tenancy.md);
- [Estrutura do projeto](docs/architecture/project-structure.md);
- [Arquitetura de dados](docs/architecture/database-architecture.md);
- [Contextos delimitados](docs/reconstruction/domain/contexts.md);
- [Plano de implementação frontend](docs/reconstruction/frontend-implementation-plan.md);
- [Roadmap único do produto](docs/ROADMAP.md).
