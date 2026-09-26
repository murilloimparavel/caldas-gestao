# Plano de implementação — Google Calendar em domínios white label

## Objetivo

Permitir que um usuário conecte o Google Calendar a partir do domínio de gestão
do tenant, usando um callback oficial único e retornando ao domínio white label
de origem sem compartilhar cookies entre hosts.

```text
Início:   https://gestao.romawear.com.br/calendar
Callback: https://gestao.caldasindica.com/google-calendar/callback
Retorno:  https://gestao.romawear.com.br/calendar?google=connected
```

## Decisões

- `GOOGLE_REDIRECT_URI` aponta somente para o domínio oficial controlado pelo
  Caldas. Não há callback dinâmico, wildcard ou OAuth Client por tenant.
- O state persiste `tenant_id`, `unit_id`, `user_id`, `redirect_uri`,
  `return_host`, `return_path`, `state_hash`, PKCE, expiração e consumo.
- O host de retorno é obtido da requisição e só é aceito se for oficial ou um
  `TenantDomain` ativo, de gestão, pertencente ao mesmo tenant.
- A rota de retorno é uma allowlist interna (`/calendar`); URLs completas,
  esquemas, portas, `//`, hosts externos e query strings não são aceitos.
- O callback oficial não exige o cookie do domínio original. Ele carrega o
  usuário pelo state, revalida membership, tenant, unidade ativa e
  `calendar.configure`, consome o state uma única vez e salva os tokens
  criptografados na conexão da unidade.
- O callback redireciona somente para o host validado no state, com apenas
  `google=connected` ou `google=error`. Tokens nunca aparecem em URL, logs,
  auditoria ou props Inertia.
- Google Calendar OAuth não é Google Login; login social fica fora deste fluxo.

## Implementação e operação

- Configurar `APP_URL`, `DOMAINS_OFFICIAL_HOSTS` e `GOOGLE_REDIRECT_URI` no
  secret store do Coolify e cadastrar somente o callback oficial no Google
  Cloud.
- Executar migrations em web/worker/scheduler antes do deploy da imagem.
- Monitorar state expirado, `invalid_grant`, recusas e hosts inválidos sem
  registrar credenciais; manter rollback que preserve conexões históricas.
- Validar manualmente o fluxo em domínio oficial e em um domínio white label
  ativo depois do deploy.

## Verificação

Os testes Pest devem cobrir início oficial e white label, retorno sem sessão,
state expirado/inválido/consumido, PKCE, usuário/tenant/unidade sem permissão,
domínio suspenso, open redirect, recusa do Google, fallback oficial,
idempotência e isolamento entre tenants. O CI deve passar com PostgreSQL,
PHPStan, Pint e os checks existentes de frontend.

## Fora de escopo

Google Login, sincronização inbound, cadastro automático de domínios no Google
Cloud, compartilhamento global de sessão e OAuth Client por tenant.
