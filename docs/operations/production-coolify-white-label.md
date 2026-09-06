# Operação de produção — Caldas Gestão White Label

Este runbook registra a operação reproduzível da aplicação no Coolify da
`vps-caldas`. Segredos, senhas e tokens ficam somente no secret store do
Coolify ou no arquivo de ambiente operacional fora do repositório.

## Topologia confirmada

- Coolify: `https://cy.caldasindica.com`
- Aplicação: `caldas-gestao`
- Worker: `caldas-gestao-worker`
- Scheduler: `caldas-gestao-scheduler`
- Build pack: imagem Docker publicada no GHCR
- Aplicação Coolify: `svurav3cvfovuz8adtv1fuwv`
- Worker Coolify: `nbhjuwa4qhs5brnwnbqg1ea2`
- Scheduler Coolify: `ni3lxtnuyk26jh3pwpfzbdiu`
- Banco PostgreSQL dedicado: `zj6ryjlxbvb237rncuxpxhuo`
- Proxy: Traefik gerenciado pelo Coolify
- Hostnames ativos:
  - `https://gestao.caldasindica.com`
  - `https://gestao.romawear.com.br`
  - `https://romawear.com.br`

## Fluxo de deploy

1. Fazer push na branch `production`.
2. O GitHub Actions executa lint, formatação, TypeScript, build e Pest.
3. O workflow publica `ghcr.io/...:sha-<commit>`.
4. O workflow atualiza a tag da aplicação no Coolify e dispara o deploy.
5. O Coolify reinicia a aplicação, aplica as migrations configuradas para a
   release e atualiza as rotas do Traefik.
6. Validar o status `running:healthy` e os endpoints públicos.

Tags por commit são obrigatórias para rollback. Não usar `latest` como única
referência de produção.

## Health check

O endpoint de health check é:

```text
GET /up
```

Ele deve responder `200` nos hostnames oficiais e personalizados. Depois de
um deploy, confirmar:

```shell
curl -fsS -o /dev/null -w '%{http_code}\n' https://gestao.caldasindica.com/up
curl -fsS -o /dev/null -w '%{http_code}\n' https://gestao.romawear.com.br/up
```

O status do Coolify é a fonte de verdade do container; uma resposta HTTP
isolada não substitui a confirmação de `running:healthy`.

## Domínio Premium

O cliente cadastra o hostname do painel. O sistema mantém o registro pendente
até confirmar simultaneamente:

1. CNAME para `vps.caldasindica.com`;
2. TXT em `_caldas-gestao-verification.<hostname>` com o token emitido;
3. hostname incluído na aplicação do Coolify;
4. certificado TLS válido.

O comando agendado `app:reconcile-tenant-domains` executa a reconciliação a
cada cinco minutos. Ele é idempotente: domínio pendente é verificado, domínio
verificado é provisionado, SSL é rechecado e somente então o domínio é
ativado. Falhas permanecem registradas no domínio e não liberam acesso.

O Traefik e a emissão/renovação do certificado são responsabilidade do
Coolify. A aplicação não cria certificados nem altera DNS do cliente.

## Banco e TLS

O runtime deve usar a role da aplicação e a conexão PostgreSQL com TLS. A
conexão administrativa de migrations deve permanecer separada da conexão de
runtime (`MIGRATION_DB_*`). Nunca executar migrations com a role da aplicação
quando o ambiente exigir privilégios administrativos.

Antes de uma release que altera schema:

```shell
php artisan migrate:status --database=migration
php artisan migrate --database=migration --force
```

Confirmar no secret store os parâmetros `DB_SSLMODE`/certificados exigidos
pelo provedor e não armazenar certificados privados no Git.

## Storage S3/MinIO

Uploads de produção devem usar o disco S3-compatible configurado no ambiente,
nunca o disco local efêmero do container. Validar antes de habilitar uploads:

- bucket e endpoint corretos;
- credenciais com menor privilégio;
- região e path-style conforme o serviço;
- leitura e gravação de um objeto de teste;
- política de acesso sem exposição pública indevida.

## Worker e scheduler

O ambiente de produção precisa executar, separadamente da aplicação web:

```shell
php artisan queue:work --sleep=1 --tries=3 --max-time=3600
php artisan schedule:work
```

O worker e o scheduler estão configurados como aplicações independentes no
Coolify e usam a mesma tag imutável da aplicação web. A imagem possui um
health check Docker de liveness comum aos três processos, que verifica a
existência do processo principal sem exigir endpoint HTTP no worker/scheduler.
O scheduler carrega a
tarefa `app:reconcile-tenant-domains` a cada cinco minutos. Sem o worker,
e-mails e jobs ficam pendentes; sem o scheduler, a reconciliação automática de
domínios não ocorre.

## Rollback

1. Identificar a última tag `sha-<commit>` saudável no GHCR.
2. Atualizar `docker_registry_image_tag` da aplicação no Coolify para essa
   tag.
3. Disparar um novo deploy pelo Coolify.
4. Confirmar `running:healthy`, `/up`, login, domínio Premium e página pública.
5. Não executar `migrate:rollback` automaticamente. Alterações de schema
   devem ser compatíveis com a estratégia expand/contract e revertidas por
   uma migration corretiva.

## Checklist pós-deploy

- [ ] workflow GitHub concluído com sucesso;
- [ ] imagem imutável publicada no GHCR;
- [ ] Coolify em `running:healthy`;
- [ ] `/up` retorna `200`;
- [ ] domínio oficial serve marketing;
- [ ] domínio Premium redireciona visitante para `/login`;
- [ ] domínio público serve a página pública do tenant;
- [ ] assets usam o hostname atual;
- [ ] logs não mostram falhas de boot, banco ou fila;
- [ ] worker e scheduler estão ativos;
- [ ] rollback para a tag anterior está disponível.

## Itens ainda dependentes de configuração operacional

- validar PostgreSQL TLS com certificado/CA do ambiente;
- validar bucket MinIO/S3 com um upload real;
- configurar mail transacional;
- executar smoke test autenticado em navegador com credencial fornecida
  manualmente pelo operador;
- integrar Lastlink e seus webhooks quando o contrato comercial estiver
  definido.
