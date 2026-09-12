# Tenant de Teste (Produção & Homologação)

Este documento registra os dados do workspace/tenant de testes provisionado em produção via VPS Contabo (`vps-caldas`).

## 1. Dados Cadastrais e Credenciais

- **Tenant ID**: `01a096e3-786f-7013-aa93-6fde4d87bf68`
- **Slug do Workspace**: `teste`
- **Nome de Exibição**: `Caldas Gestão Teste`
- **Unidade Inicial**: `matriz`
- **Fuso Horário**: `America/Sao_Paulo`
- **Moeda Padrão**: `BRL`
- **Status da Assinatura**: `active`
- **Papel do Usuário**: `owner`

### Credenciais de Acesso (Administrador)
- **Nome**: `Admin Teste`
- **E-mail**: `teste@caldasindica.com`
- **Senha Forte**: `Caldas#2026!Prod*Test@Gestao`
- **URL de Login**: [https://gestao.caldasindica.com/login](https://gestao.caldasindica.com/login)

---

## 2. Infraestrutura e Hospedagem

- **Host VPS**: `vps-caldas` (`vps.caldasindica.com` / `root@194.163.141.192`)
- **Container Coolify**: `svurav3cvfovuz8adtv1fuwv-180927548981`
- **Imagem Docker**: `ghcr.io/murilloimparavel/caldas-gestao:production`
- **Plataforma**: Coolify v4 + Docker Compose

---

## 3. Procedimento de Bootstrap Executado

O provisionamento foi executado de forma segura e idempotente via SSH na VPS através do comando interno do Laravel `app:bootstrap-tenant`:

```bash
ssh vps-caldas << 'EOF'
docker exec -i -e XDG_CONFIG_HOME=/tmp svurav3cvfovuz8adtv1fuwv-180927548981 php artisan tinker << 'EOT'
config(['bootstrap.admin_password' => 'Caldas#2026!Prod*Test@Gestao']);
$exitCode = \Illuminate\Support\Facades\Artisan::call('app:bootstrap-tenant', [
    '--tenant' => 'teste',
    '--unit' => 'matriz',
    '--name' => 'Caldas Gestão Teste',
    '--timezone' => 'America/Sao_Paulo',
    '--currency' => 'BRL',
    '--admin-name' => 'Admin Teste',
    '--admin-email' => 'teste@caldasindica.com',
    '--force' => true,
    '--no-interaction' => true,
]);
echo "Exit code: " . $exitCode . PHP_EOL;
echo "Output: " . \Illuminate\Support\Facades\Artisan::output() . PHP_EOL;
EOT
EOF
```

---

## 4. Evidência de Validação E2E

1. **Autenticação**: Login submetido via Playwright na página `/login` de produção com as credenciais acima.
2. **Redirecionamento**: Sucesso imediato com redirecionamento para `/dashboard`.
3. **Sessão & Contexto**: Sessão Laravel inicializada com `TenantContext` resolvido para `teste` e unidade `matriz`.
4. **Interface**: Header renderizou com sucesso a saudação `Olá, Admin Teste 👋` e o seletor operacional de 30 dias.
