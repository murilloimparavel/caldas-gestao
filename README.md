# Caldas Gestão

Monólito modular para gestão operacional e financeira de negócios de beleza.
O projeto está no início do scaffold: os contextos de negócio já têm fronteiras
documentadas e a implementação evolui em fatias verticais.

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

- `app/Domain/Identity`: identidade, tenant, unidade e autorização;
- `app/Domain/Customers`: clientes e histórico;
- `app/Domain/Catalog`: serviços, produtos, categorias e fornecedores;
- `app/Domain/Calendar`: disponibilidade, agenda e conflitos;
- `app/Domain/Orders`: comandas, checkout e pagamentos;
- `app/Domain/Finance`: caixa, transações, conciliação e comissões;
- `app/Domain/Analytics`: indicadores e relatórios;
- `app/Http`, `app/Models` e `database`: entrada HTTP, modelos compartilhados
  e fonte de verdade do schema;
- `resources/js/pages`: entradas Inertia; `features` concentra comportamento
  por domínio; `components` e `layouts` concentram a UI compartilhada.

## Decisões e plano

- [ADR-001 — Stack inicial](docs/adr/ADR-001--stack-inicial.md);
- [Estrutura do projeto](docs/architecture/project-structure.md);
- [Plano de implementação frontend](docs/reconstruction/frontend-implementation-plan.md);
- [Roadmap único do produto](docs/ROADMAP.md).
