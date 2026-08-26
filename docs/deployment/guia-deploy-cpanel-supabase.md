# Guia de Implantação e Deploy — Caldas Gestão (`gestao.murilloalves.com.br`)

- **Status:** Documentado & Configurado
- **Domínio:** `gestao.murilloalves.com.br`
- **Servidor cPanel:** `murilloalves.com.br` (Usuário: `millabus`)
- **Diretório no cPanel:** `/home/millabus/gestao.murilloalves.com.br/`
- **Banco de Dados:** Supabase PostgreSQL (Session Pooler + Conexão de Migração Direta)
- **Cache & Sessão:** Redis local/remoto no cPanel
- **CI/CD:** GitHub Actions + GitHub CLI (`gh secret set`)

---

## 🛠️ 1. Configuração de Variáveis de Ambiente no Servidor (`.env`)

Crie o arquivo `.env` no diretório `/home/millabus/gestao.murilloalves.com.br/.env` no cPanel com os dados de produção:

```env
APP_NAME="Caldas Gestão"
APP_ENV=production
APP_KEY=base64:COLE_SUA_APP_KEY_GERADA_AQUI
APP_DEBUG=false
APP_URL=https://gestao.murilloalves.com.br

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo

# Supabase PostgreSQL (Runtime via Session Pooler)
DB_CONNECTION=pgsql
DB_HOST=seu-projeto.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.seu-projeto
DB_PASSWORD=SUA_SENHA_DO_SUPABASE
DB_SSLMODE=require

# Supabase PostgreSQL (Conexão direta para Migrações)
MIGRATION_DB_CONNECTION=pgsql
MIGRATION_DB_HOST=db.seu-projeto.supabase.co
MIGRATION_DB_PORT=5432
MIGRATION_DB_DATABASE=postgres
MIGRATION_DB_USERNAME=postgres
MIGRATION_DB_PASSWORD=SUA_SENHA_DO_SUPABASE
MIGRATION_DB_SSLMODE=require

# Cache, Sessão e Filas com Redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

FILESYSTEM_DISK=public
VITE_APP_NAME="${APP_NAME}"
```

---

## 🔐 2. Configuração de Segredos via GitHub CLI (`gh cli`)

Execute no seu terminal local usando o `gh` para cadastrar as credenciais do cPanel diretamente no repositório do GitHub:

```bash
# Definir o Host SSH do cPanel
gh secret set CPANEL_SSH_HOST --body "murilloalves.com.br"

# Definir o Usuário SSH do cPanel
gh secret set CPANEL_SSH_USER --body "millabus"

# Definir a Chave Privada SSH (copie o conteúdo da sua id_rsa / id_ed25519)
gh secret set CPANEL_SSH_KEY < ~/.ssh/id_rsa
```

---

## ⚙️ 3. Estrutura do Subdomínio no cPanel

1. No painel cPanel de `murilloalves.com.br`, vá em **Domains / Subdomains**.
2. Adicione o subdomínio **`gestao`** apontando para o diretório `/public_html/gestao` ou `/gestao.murilloalves.com.br/public`.
3. Garanta que a versão do PHP no cPanel seja **8.4** ou **8.5** com as extensões `pdo_pgsql`, `pgsql`, `redis`, `mbstring`, `intl`, `iconv` ativas.

---

## 🚀 4. Fluxo de CI/CD (Deploy Automático)

A cada `git push origin main`, o workflow `.github/workflows/deploy-cpanel.yml` executará automaticamente:
1. Instalação das dependências PHP (`composer install --no-dev`).
2. Build de produção dos assets frontend via Vite (`npm run build`).
3. Sincronização limpa via `rsync` para `/home/millabus/gestao.murilloalves.com.br/`.
4. Execução de migrações (`php artisan migrate --force`) e cache dos arquivos de rotas/configurações.
