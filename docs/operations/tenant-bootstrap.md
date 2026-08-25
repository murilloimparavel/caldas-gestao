# Bootstrap do primeiro tenant

`app:bootstrap-tenant` cria o primeiro workspace pela conexão Laravel e é
idempotente para a mesma combinação de tenant, unidade e administrador.
O comando não aceita senha como argumento, não imprime e-mail/nome do
administrador e nunca executa DDL ou conecta diretamente ao Supabase.

## Pré-requisitos

Execute migrations pela conexão administrativa direta antes do bootstrap:

```shell
php artisan db:provision-schema --database=migration --no-interaction
php artisan migrate --database=migration --force
```

Confira o host, o projeto e o banco antes de executar. O runtime usa o Session
Pooler; migrations e provisionamento usam `MIGRATION_DB_*`. Nenhum comando
deste runbook executa migration remota por conta própria.

## Execução interativa

Em desenvolvimento ou em uma janela controlada:

```shell
php artisan app:bootstrap-tenant \
  --tenant=caldas-centro \
  --unit=matriz \
  --name="Caldas Centro" \
  --timezone=America/Sao_Paulo \
  --currency=BRL \
  --admin-name="Administrador inicial" \
  --admin-email=admin@example.com
```

Sem `BOOTSTRAP_TENANT_PASSWORD`, a senha é solicitada em prompt secreto e
confirmada sem ser exibida. Para automação controlada, injete a variável pelo
secret store:

```shell
BOOTSTRAP_TENANT_PASSWORD='secret-from-a-secret-store' \
php artisan app:bootstrap-tenant --tenant=caldas-centro --unit=matriz \
  --name="Caldas Centro" --admin-name="Administrador inicial" \
  --admin-email=admin@example.com --no-interaction
```

Não coloque esse valor em `.env.example`, scripts, CI logs ou histórico de
shell. Em produção, o comando exige `--force`:

```shell
php artisan app:bootstrap-tenant ... --force
```

Não existe senha padrão de produção. O administrador novo é marcado como
verificado porque o bootstrap ocorre em uma janela operacional controlada; a
política de senha Laravel continua sendo aplicada.

A moeda é validada contra o catálogo explícito de `config/bootstrap.php`.
Nesta fase somente `BRL` é suportada; adicionar outra moeda exige decisão de
produto e cobertura de formatação/liquidação antes de alterar o catálogo.

## Idempotência e reconciliação

- Tenant inexistente: cria usuário global (ou reutiliza o e-mail normalizado),
  tenant, unidade, membership ativa, owner, catálogo de permissões, auditoria
  e outbox dentro de transações.
- Repetição da mesma combinação já completa: retorna sucesso sem criar linhas
  ou eventos adicionais.
- Slug existente incompleto: falha por padrão para preservar a semântica
  create-only de `OnboardTenant`.
- Para uma reconciliação autorizada, use `--resume`. O comando revalida a
  membership ativa e o papel owner antes de chamar `ResumeTenantOnboarding`.
- Slug existente com outro administrador, ou sem a identidade owner
  identificável, falha sem criar/reassociar usuários.

O parâmetro `--resume` não transforma onboarding em ingresso implícito. Ele é
uma escolha explícita de reconciliação e produz seu próprio evento auditável e
outbox quando altera o tenant.

## Seed local

`php artisan db:seed` chama o catálogo e cria uma fixture somente em
`local`/`testing`:

- usuário: `teste@caldas.local`;
- senha local: `Caldas@2026!`;
- tenant: `teste`;
- unidade: `matriz`.

Essa credencial existe apenas para desenvolvimento/testes locais. Em qualquer
outro ambiente `DatabaseSeeder` não cria usuário, tenant, unidade ou permissão.
Use o comando de bootstrap com um segredo fornecido pelo ambiente.
