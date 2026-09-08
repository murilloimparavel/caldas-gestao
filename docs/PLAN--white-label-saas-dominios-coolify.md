# Plano de evolução — Caldas Gestão como plataforma multiempresa por domínio

## 1. Objetivo

Transformar o Caldas Gestão em uma plataforma multiempresa com:

- página comercial em `gestao.caldasindica.com`;
- contratação por checkout externo, inicialmente Lastlink;
- provisionamento automático após pagamento aprovado;
- acesso pelo e-mail usado na compra;
- senha temporária para o primeiro acesso;
- troca obrigatória de senha;
- onboarding do novo tenant;
- controle de acesso conforme o estado da assinatura;
- deploy por imagem Docker compilada no GitHub e publicada no GHCR;
- execução da produção no Coolify da `vps-caldas`;
- painel Premium em domínio próprio, como `gestao.cliente.com`;
- site público e agendamento em domínio próprio, como `cliente.com` ou `www.cliente.com`.

O produto não será um white label para o cliente revender. Será uma única plataforma operada pelo Caldas Gestão, com identidade, domínio e dados separados por tenant.

## 2. Estado atual identificado

### Aplicação

- Laravel 13, PHP compatível com 8.5;
- React 19, TypeScript, Vite, Inertia 3;
- Fortify para autenticação;
- Wayfinder para rotas frontend;
- PostgreSQL/Supabase previsto para produção;
- Redis previsto para filas, cache e sessões;
- Pest, PHPStan/Larastan, Pint, ESLint, Prettier e build frontend.

### Fundação de domínio já existente

- tenancy por `tenant_id`;
- usuários globais e memberships;
- RBAC e Policies;
- `TenantContext`;
- `TenantSubscription`;
- `TenantBillingAccount`;
- `BillingWebhookEvent`;
- `SaaSBillingService`;
- entitlements de acesso;
- onboarding via `OnboardTenant`;
- comando `billing:reconcile`;
- middleware de restrição de acesso SaaS;
- booking público e configurações de branding.

### Infraestrutura atual

- workflow de deploy atual para cPanel via rsync/SSH;
- produção ainda não está configurada no repositório como imagem Docker;
- `.env` local usa SQLite, fila/database e mail em log;
- Coolify disponível na `vps-caldas`, com documentação e skill local;
- configuração compartilhada do Coolify está fora do repositório, em `/Users/murilloalves/Projects/CRCN/.env`;
- a API do Coolify precisa ser validada antes de qualquer criação ou alteração de recurso.

### Cuidados iniciais

- O working tree atualmente possui mudanças e arquivos não commitados. A migração não deve apagar, resetar ou sobrescrever esse trabalho.
- Não executar deploy real até confirmar o commit/branch que representa a versão aprovada.
- Não colocar secrets, tokens, senhas ou dumps reais no repositório, na imagem Docker ou neste documento.

## 3. Decisões de arquitetura

### 3.1 Contextos de assinatura

Manter separados:

- `TenantSubscription`: assinatura do negócio com o Caldas Gestão;
- `CustomerSubscription`: assinatura de um cliente final do negócio.

O billing SaaS nunca deve usar o agregado de assinaturas de clientes finais.

### 3.2 Identidade

O `User` continua sendo global. A empresa será um `Tenant`, ligado ao usuário por `Membership`.

O e-mail usado no checkout será o e-mail principal de acesso. Se ele já existir, o sistema deve poder adicionar uma nova membership/empresa ao usuário, desde que o fluxo seja idempotente e auditado.

### 3.3 Billing

A Lastlink será a autoridade de cobrança. O Caldas Gestão será a autoridade de autorização de acesso.

Fluxo:

```text
Checkout Lastlink
        ↓
Webhook autenticado
        ↓
Evento persistido de forma idempotente
        ↓
Normalização do payload
        ↓
Provisionamento ou alteração de assinatura
        ↓
Entitlement saas.access
        ↓
Autorização no app
```

O vínculo deve priorizar IDs externos estáveis da Lastlink. E-mail é identificador de comunicação e fallback controlado, não a única chave de reconciliação.

### 3.4 Domínios

Domínios personalizados serão registros próprios, preferencialmente em `tenant_domains`, e não somente uma coluna em `tenants`.

Tipos mínimos:

- `management`: painel privado, como `gestao.cliente.com`;
- `public`: site público e agendamento, como `www.cliente.com`.

Estados mínimos:

- `pending`;
- `verified`;
- `active`;
- `disabled`;
- `suspended`.

O domínio resolve o tenant, mas nunca concede acesso sozinho. Membership, Policy, entitlement e autenticação continuam obrigatórios.

### 3.5 Deploy

O GitHub Actions será responsável por testar e compilar a imagem. O GHCR será o registry. O Coolify baixará e executará a imagem na `vps-caldas`.

```text
Push aprovado
    ↓
CI: lint, PHPStan, testes e build
    ↓
Build da imagem Docker
    ↓
Push no GHCR com tag imutável
    ↓
Deploy do Coolify
    ↓
Migrations controladas
    ↓
Web, worker e scheduler
```

Produção deve usar tags por commit, por exemplo `sha-<commit>`, e não depender apenas de `latest`.

## 4. Modelo de ambientes

### Domínios técnicos

Inicialmente:

```text
gestao.caldasindica.com  Marketing, login e fallback do painel
```

Preferencialmente, separar depois:

```text
app.caldasindica.com     Origem técnica dos painéis
public.caldasindica.com  Origem técnica dos sites públicos
```

### Domínios Premium

```text
gestao.cliente.com       Painel administrativo
www.cliente.com          Site público
cliente.com              Redirecionamento para www, se necessário
```

### DNS

Para subdomínios:

```text
gestao.cliente.com  CNAME  app.caldasindica.com
www.cliente.com     CNAME  public.caldasindica.com
```

Para o domínio raiz, usar `www` com redirecionamento ou um provedor que ofereça ALIAS/ANAME/proxy DNS. CNAME tradicional não deve ser exigido para o apex.

## 5. Sprints

## Sprint 0 — Pré-voo, decisões e proteção do estado atual

### Objetivo

Congelar decisões e identificar o estado real da aplicação, do banco, do cPanel e do Coolify.

### Entregas

- confirmar branch e commit de referência;
- separar mudanças existentes do trabalho desta iniciativa;
- confirmar local do banco atual e se o Supabase já é a fonte oficial;
- confirmar domínio atualmente em produção;
- confirmar recursos existentes no Coolify da `vps-caldas`;
- confirmar projeto, environment, servidor e destino no Coolify;
- confirmar disponibilidade de PostgreSQL, Redis e storage;
- confirmar método de integração GitHub/GHCR;
- confirmar eventos e autenticação dos webhooks Lastlink;
- fechar catálogo de planos, produtos e ofertas;
- definir carência e comportamento de inadimplência;
- definir escopo de cada plano Premium;
- registrar decisões que mudarem o ADR atual.

### Critérios de aceite

- existe um inventário de produção aprovado;
- existe plano de rollback do deploy;
- não há secret exposto no Git;
- o banco de produção e a estratégia de migração estão identificados;
- os eventos Lastlink necessários têm payloads de exemplo sanitizados.

## Sprint 1 — Containerização e pipeline GitHub/GHCR

### Objetivo

Produzir uma imagem reproduzível e validada antes de migrar o tráfego.

### Entregas

- definir Dockerfile multi-stage ou estratégia equivalente;
- incluir PHP 8.5 e extensões necessárias;
- instalar dependências Composer sem dev na imagem final;
- compilar React/Vite dentro do pipeline;
- não incluir `.env` ou secrets na imagem;
- adicionar health check HTTP;
- definir entrypoint e comandos de runtime;
- criar workflow de CI para lint, PHPStan, Pest e build;
- publicar imagem privada no GHCR;
- publicar tags por commit;
- documentar como fazer rollback para uma tag anterior.

### Critérios de aceite

- imagem inicia localmente;
- health check responde corretamente;
- aplicação serve assets compilados;
- testes rodam antes do push da imagem;
- imagem não contém secrets;
- uma tag imutável pode ser executada sem Node ou Composer instalados no servidor.

## Sprint 2 — Infraestrutura de produção no Coolify

### Objetivo

Executar uma cópia controlada do app no Coolify sem ainda trocar o domínio principal.

### Entregas

- criar ou reutilizar aplicação no projeto `caldas_indica` e environment `production`;
- configurar a imagem do GHCR;
- configurar secrets e variáveis de produção no Coolify;
- configurar PostgreSQL/Supabase;
- configurar Redis;
- configurar storage persistente ou S3-compatible;
- configurar mail transacional;
- configurar health check;
- configurar limites de CPU/memória;
- configurar logs e notificações de deploy;
- criar worker com a mesma imagem;
- criar scheduler para `schedule:run`;
- definir procedimento de migration única por release;
- testar conectividade e permissões do banco.

### Variáveis essenciais a validar

```text
APP_ENV
APP_KEY
APP_URL
DB_CONNECTION
DB_HOST
DB_DATABASE
DB_USERNAME
DB_PASSWORD
DB_SCHEMA
DB_SEARCH_PATH
MIGRATION_DB_*
REDIS_*
QUEUE_CONNECTION
CACHE_STORE
SESSION_DRIVER
MAIL_*
FILESYSTEM_DISK
LASTLINK_*
```

### Critérios de aceite

- aplicação sobe no endereço temporário do Coolify;
- banco responde com role correta;
- migrations são executadas por conexão administrativa controlada;
- web, worker e scheduler iniciam;
- fila processa um job de teste;
- e-mail transacional chega em ambiente de teste;
- health checks e logs são suficientes para diagnosticar falha.

## Sprint 3 — Migração de tráfego cPanel → Coolify

### Objetivo

Colocar o ambiente Coolify em produção com risco controlado.

### Entregas

- reduzir TTL DNS antes da janela de troca;
- criar backup e confirmar restauração;
- confirmar schema e dados no banco de produção;
- executar migrations conforme expand-contract;
- configurar `gestao.caldasindica.com` no Coolify;
- ativar HTTPS;
- testar login, dashboard, booking público, uploads, e-mail e filas;
- trocar DNS para o novo destino;
- monitorar logs, erros, latência e filas;
- manter cPanel disponível durante a janela de rollback;
- desativar deploy automático do cPanel somente após estabilização.

### Critérios de aceite

- domínio padrão responde pelo Coolify;
- SSL válido e redirecionamento HTTP → HTTPS funcionam;
- usuários existentes conseguem autenticar;
- dados existentes permanecem íntegros;
- nenhuma requisição de produção depende do cPanel;
- rollback documentado e testado em staging.

## Sprint 4 — Página de vendas e catálogo comercial

### Objetivo

Permitir que novos clientes entendam e escolham um plano.

### Entregas

- landing page em `gestao.caldasindica.com`;
- planos comerciais e recursos;
- CTA para checkout externo;
- configuração dos links Lastlink por plano;
- páginas de sucesso, pendência e cancelamento;
- tracking básico de origem da campanha;
- links de login em `/signin`;
- separação visual entre site comercial e área autenticada.

### Critérios de aceite

- visitante consegue escolher um plano;
- checkout abre com o produto correto;
- nenhum tenant é criado somente por clicar no botão;
- páginas de retorno não afirmam pagamento aprovado sem confirmação do webhook.

## Sprint 5 — Lastlink, webhooks e billing SaaS

### Objetivo

Receber e aplicar eventos de cobrança de forma segura, idempotente e auditável.

### Entregas

- autenticar webhook por segredo/assinatura conforme contrato Lastlink;
- validar payload e tamanho da requisição;
- persistir envelope bruto sanitizado;
- separar recebimento do processamento;
- normalizar eventos externos para eventos internos;
- mapear produto/oferta para `PlatformPlan`;
- vincular comprador por IDs externos;
- tratar eventos duplicados;
- tratar eventos fora de ordem;
- registrar falhas e permitir reprocessamento;
- manter `BillingWebhookEvent` como inbox de billing;
- publicar eventos de domínio após commit;
- atualizar `TenantSubscription` e `saas.access`;
- atualizar `billing:reconcile` para cobrir divergências.

### Estados sugeridos

```text
trial → active → grace → suspended → expired
                 ↘ cancelled
```

Os nomes finais devem ser compatíveis com o modelo já existente e com o contrato comercial.

### Critérios de aceite

- evento sem autenticação é rejeitado;
- evento duplicado não cria novo tenant nem nova assinatura;
- evento inválido não altera billing;
- pagamento aprovado ativa acesso;
- renovação mantém acesso;
- atraso entra em carência;
- vencimento restringe acesso;
- cancelamento não apaga dados;
- pagamento posterior reativa o tenant;
- processamento pode ser repetido sem efeitos duplicados.

## Sprint 6 — Provisionamento pós-compra

### Objetivo

Transformar uma compra aprovada em um tenant utilizável.

### Entregas

- Action idempotente `ProvisionTenantFromPurchase`;
- localizar ou criar usuário pelo e-mail normalizado;
- criar tenant;
- criar membership de owner;
- provisionar papéis e permissões;
- criar `TenantBillingAccount`;
- criar `TenantSubscription` com snapshots externos;
- associar plano e entitlements;
- gerar credencial temporária;
- registrar estado de provisionamento;
- criar outbox event;
- enfileirar e-mail de boas-vindas;
- tratar compra repetida e retry;
- criar fila/revisão manual para casos ambíguos.

### Critérios de aceite

- compra aprovada cria exatamente um tenant;
- retry do mesmo evento retorna o mesmo resultado;
- usuário existente pode receber nova membership sem duplicação;
- plano contratado é preservado como snapshot;
- falha transacional não deixa tenant parcialmente criado;
- processo é auditado e correlacionável com o evento Lastlink.

## Sprint 7 — Primeiro acesso, senha temporária e onboarding

### Objetivo

Entregar a experiência completa do novo cliente.

### Entregas

- e-mail para o endereço usado na compra;
- senha temporária aleatória;
- armazenamento somente com hash;
- expiração da senha temporária;
- marcação `must_change_password` ou entidade equivalente;
- tela obrigatória de troca de senha;
- invalidação imediata da senha temporária;
- logout/reautenticação após troca, se necessário;
- onboarding resumível;
- onboarding idempotente;
- criação/configuração inicial de unidade;
- branding básico;
- serviços, profissionais e horários iniciais;
- redirecionamento para dashboard.

### Critérios de aceite

- senha temporária nunca aparece em logs;
- primeiro login exige troca;
- rotas administrativas ficam bloqueadas até a troca;
- senha expirada exige novo fluxo de ativação ou recuperação;
- usuário consegue continuar onboarding depois;
- tenant parcialmente configurado não quebra o sistema;
- ao finalizar, usuário chega ao painel correto.

## Sprint 8 — Modelo de domínios e resolução por hostname

### Objetivo

Implementar a camada de domínio no Laravel antes da automação completa do proxy.

### Entregas

- migration e model `TenantDomain`;
- normalização de hostname;
- validação de formato e limites;
- unicidade global de hostname;
- tipos `management` e `public`;
- status e transições controladas;
- token TXT de verificação;
- CNAME target configurável;
- serviço de consulta DNS com timeout;
- Action de verificar domínio;
- middleware de resolução por `Host`;
- fallback somente para domínios oficiais;
- rejeição de host desconhecido;
- bloqueio de domínio suspenso;
- preservação do `TenantContext` e das Policies;
- cache curto de resolução com invalidação.

### Critérios de aceite

- domínio malformado é rejeitado;
- hostname de outro tenant nunca resolve para o tenant errado;
- domínio pendente não serve conteúdo privado;
- domínio suspenso é bloqueado;
- domínio ativo resolve o tenant correto;
- domínio padrão continua funcionando;
- requisição sem hostname conhecido não expõe dados;
- testes negativos cobrem isolamento entre tenants.

## Sprint 9 — CNAME, SSL e ativação de domínio Premium

### Objetivo

Permitir que o cliente configure seu domínio e o use de verdade.

### Entregas

- tela de cadastro do domínio Premium;
- instruções DNS geradas pelo sistema;
- CNAME para subdomínios;
- TXT de verificação de posse;
- verificação manual e rechecagem;
- integração com proxy/Coolify/Cloudflare definida;
- inclusão do hostname no recurso de produção;
- emissão e renovação de certificado;
- estado de SSL separado do estado DNS;
- ativação somente depois de DNS e SSL válidos;
- remoção/desativação auditada;
- fallback para `gestao.caldasindica.com` durante configuração.

### Fluxo de cliente

```text
Cliente informa gestao.cliente.com
        ↓
Sistema exibe CNAME e TXT
        ↓
Cliente configura DNS
        ↓
Sistema verifica posse e apontamento
        ↓
Proxy recebe hostname
        ↓
SSL é emitido
        ↓
Domínio fica active
```

### Critérios de aceite

- CNAME correto pode ser detectado;
- CNAME ausente ou incorreto mostra diagnóstico útil;
- TXT incorreto impede verificação;
- domínio não verificado não é ativado;
- certificado válido é requisito para status ativo;
- hostname pode ser desativado sem afetar o domínio padrão;
- domínio Premium sem entitlement não pode ser cadastrado/ativado.

## Sprint 10 — Painel em domínio próprio

### Objetivo

Entregar `gestao.cliente.com` como entrada administrativa Premium.

### Entregas

- roteamento de login e painel pelo domínio personalizado;
- redirecionamento seguro de rotas incompatíveis;
- branding do tenant;
- políticas e memberships preservadas;
- cookies e sessão revisados;
- URLs geradas corretamente;
- proteção contra host header injection;
- comportamento durante assinatura suspensa;
- tela de billing acessível quando o painel estiver bloqueado.

### Critérios de aceite

- painel do Tenant A nunca mostra dados do Tenant B;
- usuário sem membership não acessa painel;
- assinatura suspensa não libera recursos operacionais;
- billing, suporte, logout e recuperação continuam acessíveis;
- domínio padrão continua sendo fallback funcional;
- links internos não vazam para outro tenant.

## Sprint 11 — Site público e agendamento por domínio

### Objetivo

Entregar o domínio público do cliente para clientes finais.

### Entregas

- resolução `public` separada de `management`;
- página pública com branding;
- catálogo público;
- profissionais e disponibilidade;
- agendamento;
- confirmações;
- rate limiting e proteção contra abuso;
- SEO e metatags por tenant;
- suporte para `www`;
- redirect do apex quando aplicável;
- isolamento entre sites públicos.

### Critérios de aceite

- visitante consegue agendar sem login administrativo;
- somente serviços públicos são exibidos;
- site público não expõe dados privados;
- tenant suspenso tem comportamento definido e consistente;
- dois domínios públicos exibem tenants diferentes corretamente.

## Sprint 12 — Operação, reconciliação e escala

### Objetivo

Tornar a plataforma operável após os primeiros clientes.

### Entregas

- painel interno de tenants;
- busca por e-mail, domínio e ID externo;
- visualização de assinatura e eventos;
- reprocessamento de webhook;
- reenvio de e-mail de primeiro acesso;
- troca de plano auditada;
- bloqueio manual auditado;
- métricas de provisionamento;
- métricas de filas e webhooks;
- alertas de erro de SSL/DNS;
- backups e teste de restauração;
- runbook de incidente;
- retenção e minimização de PII;
- revisão de custos de Coolify, banco, storage e e-mail.

## 6. Modelo mínimo de dados adicional

### `tenant_domains`

Campos sugeridos:

```text
id
tenant_id
hostname
kind                  management|public
status                pending|verified|active|disabled|suspended
verification_token
expected_cname
verified_at
activated_at
disabled_at
ssl_status
ssl_verified_at
last_dns_check_at
last_dns_error
metadata
created_at
updated_at
```

Restrições:

- `hostname` único globalmente;
- hostname normalizado em lowercase, sem protocolo e sem path;
- domínio Premium exige entitlement;
- apenas um domínio primário de cada tipo por tenant;
- transições de status passam por Action autorizada;
- nenhum domínio desativado resolve conteúdo.

### Acesso temporário

Pode ser implementado inicialmente em `users`, desde que o estado seja bem definido:

```text
must_change_password
temporary_password_expires_at
first_login_at
```

Se o fluxo crescer para convites e múltiplos administradores, extrair para uma entidade de convite/ativação será preferível.

## 7. Testes obrigatórios

### Billing

- webhook sem segredo;
- payload inválido;
- evento duplicado;
- evento fora de ordem;
- pagamento aprovado;
- renovação;
- atraso;
- expiração;
- cancelamento;
- chargeback/reembolso;
- reativação;
- falha e reprocessamento.

### Provisionamento

- compra cria tenant;
- retry não duplica tenant;
- e-mail existente;
- mesmo e-mail em compras diferentes;
- rollback transacional;
- outbox após commit;
- e-mail enfileirado.

### Autenticação

- senha temporária funciona uma vez;
- troca obrigatória;
- expiração;
- recuperação de senha;
- acesso bloqueado durante troca;
- MFA compatível com primeiro acesso.

### Domínios

- hostname inválido;
- CNAME correto;
- CNAME incorreto;
- TXT incorreto;
- DNS indisponível;
- domínio pendente;
- domínio ativo;
- domínio suspenso;
- domínio duplicado;
- host desconhecido;
- Tenant A versus Tenant B;
- domínio público versus painel.

### Infraestrutura

- imagem inicia;
- health check;
- migration de release;
- worker;
- scheduler;
- Redis;
- storage;
- mail;
- rollback de imagem;
- backup e restauração.

## 8. Definition of Done global

Cada sprint só será considerada concluída quando:

- regras são aplicadas no servidor;
- há testes Pest para fluxos críticos;
- isolamento de tenant foi testado positivamente e negativamente;
- estados loading, empty e error existem no frontend;
- eventos e alterações sensíveis são auditados;
- logs não expõem secrets ou PII desnecessária;
- migrations seguem expand-contract;
- Pint, PHPStan, ESLint, Prettier e build passam;
- o deploy usa imagem versionada;
- há procedimento de rollback;
- o comportamento de falha está definido;
- documentação operacional foi atualizada quando necessário.

## 9. Ordem de execução recomendada

```text
Sprint 0  Pré-voo e decisões
Sprint 1  Docker + GitHub + GHCR
Sprint 2  Coolify e serviços de produção
Sprint 3  Migração cPanel → Coolify
Sprint 4  Página de vendas
Sprint 5  Lastlink e webhooks
Sprint 6  Provisionamento
Sprint 7  Primeiro acesso e onboarding
Sprint 8  Modelo de domínios e hostname
Sprint 9  CNAME, SSL e ativação
Sprint 10 Painel Premium por domínio
Sprint 11 Site público por domínio
Sprint 12 Operação e escala
```

## 10. Critério para liberar os primeiros clientes Premium

Só liberar domínio próprio para clientes reais quando:

- o app estiver rodando no Coolify;
- deploy por imagem estiver funcionando;
- backup e restauração tiverem sido testados;
- webhook Lastlink estiver idempotente;
- provisionamento estiver idempotente;
- troca obrigatória de senha estiver funcionando;
- um tenant não conseguir acessar dados de outro;
- DNS e SSL tiverem fluxo de diagnóstico;
- suspensão de assinatura bloquear o painel;
- domínio padrão continuar funcionando como fallback;
- houver monitoramento de web, worker, scheduler, banco e SSL.

## 11. Primeira fatia prática recomendada

A primeira execução deve ser a Sprint 0, seguida imediatamente por um spike técnico de infraestrutura:

1. consultar o Coolify da `vps-caldas`;
2. confirmar recursos existentes;
3. confirmar acesso ao GHCR;
4. construir uma imagem sem alterar produção;
5. subir uma aplicação temporária no Coolify;
6. validar Laravel, Inertia, PostgreSQL, Redis e fila;
7. somente então iniciar a troca de produção.

Não iniciar o cadastro de domínios Premium antes de provar que o proxy do Coolify aceita múltiplos hostnames e que o SSL automático pode ser operado de forma confiável.
