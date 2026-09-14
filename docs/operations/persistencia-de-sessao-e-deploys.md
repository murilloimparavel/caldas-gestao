# Guia Operacional — Persistência de Sessões em Deploys e Releases
## Sistema: Caldas Gestão

- **Data de Homologação**: 2026-09-14
- **Ambiente**: Produção (`vps-caldas` / Coolify `cy.caldasindica.com`)
- **Status**: Homologado e Ativo em Produção

### 1. Contexto do Problema Resolvido

Anteriormente, a aplicação utilizava o driver padrão `SESSION_DRIVER=file`. Em arquiteturas baseadas em containers Docker orquestrados por Coolify, cada release ou deploy reconstrói a imagem e destrói o container anterior. Como a pasta `/var/www/html/storage/framework/sessions` ficava dentro do container efêmero, todas as sessões ativas eram deletadas a cada deploy, deslogando imediatamente todos os operadores conectados.

### 2. Arquitetura de Sessão Implementada

- **Driver Centralizado**: `SESSION_DRIVER=database` persistido na tabela `sessions` do PostgreSQL (`zj6ryjlxbvb237rncuxpxhuo`).
- **Sobrevivência a Deploys**: Como o PostgreSQL é dedicado e permanente, múltiplos deploys, reinicializações ou escalonamentos de containers não afetam as sessões ativas.
- **Duração de Inatividade**: 120 minutos (`SESSION_LIFETIME=120`), renovado a cada requisição pelo campo `last_activity`.
- **Remember Me de Longo Prazo**: Cookie criptografado `remember_web_*` com validade de até 5 anos no navegador do cliente, associado à coluna `remember_token` da tabela `users`.
- **Segurança de Cookies**:
  - `HttpOnly = true` (blindagem contra roubo de sessão via XSS).
  - `SameSite = lax` (mitigação de CSRF).
  - `Secure = true` (tráfego restrito a conexões HTTPS).
  - `Serialization = json` (proteção contra gadget chains / PHP Object Injection).

### 3. Matriz de Variáveis no Coolify

Serviços configurados com as variáveis de sessão:
- `caldas-gestao` (`svurav3cvfovuz8adtv1fuwv`)
- `caldas-gestao-worker` (`nbhjuwa4qhs5brnwnbqg1ea2`)
- `caldas-gestao-scheduler` (`ni3lxtnuyk26jh3pwpfzbdiu`)

Variáveis configuradas:

| Variável | Valor de Produção | Finalidade |
|---|---|---|
| `SESSION_DRIVER` | `database` | Salva sessões na tabela `sessions` do PostgreSQL |
| `SESSION_LIFETIME` | `120` | Timeout de inatividade de 2 horas |
| `SESSION_SECURE_COOKIE` | `true` | Exige HTTPS para envio do cookie |
| `SESSION_COOKIE` | `caldas-gestao-session` | Nome exclusivo do cookie de sessão |

### 4. Procedimentos de Validação e Auditoria

- Como verificar o driver ativo no container:
  ```shell
  docker exec <container_id> env | grep SESSION_
  ```

- Como consultar a tabela de sessões no banco:
  ```shell
  docker exec <container_id> php artisan tinker --execute 'echo DB::table("sessions")->count();'
  ```

- Como inspecionar headers de cookie em requisições:
  ```shell
  curl -sI https://gestao.caldasindica.com/login | grep -i set-cookie
  ```
