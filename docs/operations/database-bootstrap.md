# Bootstrap do banco de dados

Esta runbook cobre a baseline de identidade da Wave F2. Ela não cria tabelas de domínio e não autoriza migrations no projeto remoto.

## Conexões

- `DB_*` é a conexão de runtime. Em produção, deve apontar para o Session Pooler do Supabase.
- `MIGRATION_DB_*` é a conexão administrativa de curta duração. Deve apontar para o endpoint direto do PostgreSQL.
- `DB_SCHEMA=app` e `DB_SEARCH_PATH=app,public` mantêm tabelas da aplicação fora de `public`.
- `DB_RUNTIME_ROLE` identifica a role de execução que receberá apenas `USAGE` no schema, DML nas tabelas e uso das sequences. Ela não recebe `CREATE` nem ownership.
- `DB_PROVISION_STRICT=true` faz o provisionamento falhar quando a role de runtime não está configurada. Produção é sempre strict, independentemente desse valor.
- `DB_SSLMODE=require` e `MIGRATION_DB_SSLMODE=require` são os padrões seguros. Apenas o PostgreSQL efêmero do CI usa `disable`.
- Segredos pertencem ao secret store do ambiente e nunca ao repositório.

## Primeira instalação

Depois de configurar as duas conexões, execute em um ambiente controlado:

```shell
php artisan db:provision-schema --database=migration --no-interaction
php artisan migrate --database=migration --force
```

O primeiro comando aceita somente identificadores PostgreSQL simples e usa `CREATE SCHEMA IF NOT EXISTS`, portanto pode ser repetido sem apagar objetos. Quando `DB_RUNTIME_ROLE` está configurada, ele verifica `current_user` pela conexão de runtime, concede privilégios mínimos sobre tabelas/sequences existentes e configura default privileges para objetos criados depois pela role de migration. A role precisa existir e aceitar a conexão de runtime; o comando não cria roles nem presume superuser. O segundo comando usa a conexão direta, não o pooler de runtime.

Antes de qualquer execução remota, confira o host, o projeto Supabase e o banco alvo. Esta wave deve ser validada primeiro no PostgreSQL do CI. O comando de provisionamento concede somente os privilégios descritos acima; não altera schemas gerenciados pelo Supabase e não executa migrations.

No Session Pooler do Supabase, `DB_USERNAME` pode conter o sufixo exigido pelo endpoint, como `caldas_runtime.<project-ref>`. `DB_RUNTIME_ROLE` continua sendo o nome PostgreSQL sem sufixo, `caldas_runtime`. A verificação usa `select current_user`, não compara literalmente o username de conexão nem presume `SET ROLE`.

## Desenvolvimento e CI

O desenvolvimento local continua usando SQLite como ciclo rápido. O workflow do GitHub sobe PostgreSQL real, cria uma role runtime com `LOGIN`, provisiona `app`, instala a baseline do zero e executa toda a suíte. Os resets de schema disparados por `RefreshDatabase` ou `LazilyRefreshDatabase` usam a conexão `migration`; requests, factories, transações e assertions continuam na conexão runtime. Esse gate verifica UUID nativo, `search_path` e a ausência de `CREATE` para runtime; comportamento específico de PostgreSQL não é considerado validado por SQLite.

Como a baseline ainda não foi implantada remotamente, as migrations iniciais foram coordenadas diretamente para UUIDv7. Depois do primeiro deploy, migrations publicadas tornam-se imutáveis e toda mudança passa a ser feita por uma nova migration expand/backfill/contract.

## Rollback controlado

Em banco descartável, valide reversibilidade com:

```shell
php artisan migrate --database=migration --force
php artisan migrate:rollback --database=migration --force
php artisan migrate --database=migration --force
```

Não execute `migrate:fresh`, `db:wipe`, rollback ou drop de schema em Supabase remoto sem uma autorização explícita e uma confirmação independente do alvo.

## Fronteira dos testes de passkeys

Os testes HTTP cobrem a geração real das opções de registro e login, persistência da cerimônia na sessão, remoção, evento e autorização de ownership. O pipeline HTTP de login também é coberto com uma fixture WebAuthn estruturada e o `VerifyPasskey` substituído no container; isso comprova desserialização, consumo da sessão, resposta e autenticação do guard, mas deliberadamente não comprova assinatura.

A conclusão criptográfica de registro/login não é simulada como se fosse prova real: ela exige uma credencial assinada por um authenticator WebAuthn e valida challenge, origem, relying party, contador e assinatura. Esse trecho deve ser coberto por teste de navegador com authenticator virtual (CDP/Playwright) em uma wave de browser dedicada. Até esse gate existir, não alegamos cobertura end-to-end da cerimônia criptográfica.

O 2FA TOTP não possui essa limitação: a suíte conclui autenticação com um código TOTP válido e com recovery code, confirmando também o consumo do recovery code.
