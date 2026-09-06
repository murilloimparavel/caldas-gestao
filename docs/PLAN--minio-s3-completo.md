# Plano de migração completa para MinIO/S3 — Caldas Gestão

## 1. Objetivo

Migrar todo o armazenamento de arquivos do Caldas Gestão para o MinIO
compatível com S3, mantendo uma abstração única no Laravel e garantindo:

- uploads persistentes fora do filesystem efêmero dos containers;
- isolamento por tenant nos caminhos dos objetos;
- credenciais com menor privilégio;
- URLs de mídia funcionais no painel e no site público;
- substituição e remoção de arquivos sem deixar órfãos;
- testes automatizados de upload, leitura, deleção e isolamento;
- deploy reproduzível no Coolify para web, worker e scheduler;
- migração segura dos arquivos existentes e rollback controlado.

Este plano não torna o bucket público por padrão. A aplicação deve mediar os
uploads e decidir quando uma URL pública ou temporária pode ser emitida.

## 2. Estado atual confirmado

- MinIO está saudável no Coolify da `vps-caldas`.
- Bucket privado criado: `caldas-gestao-media`.
- As credenciais do MinIO foram copiadas como secrets para web, worker e
  scheduler no Coolify.
- O endpoint interno configurado é o hostname do container MinIO na rede do
  Coolify, usando a porta S3 `9000`.
- O aplicativo mantém `FILESYSTEM_DISK=local` por padrão e usa a nova variável
  `MEDIA_DISK=public` no ambiente local.
- O pacote `league/flysystem-aws-s3-v3` já foi instalado e está registrado no
  `composer.lock`.
- Uploads atuais usam principalmente o disk `public`, incluindo imagens de
  serviços, produtos, profissionais e galeria/cover do agendamento.
- Os modelos persistem caminhos relativos e alguns accessors ainda chamam
  explicitamente `Storage::disk('public')`.

## 3. Decisões técnicas

### 3.1 Driver e configuração

Usar o driver `s3` do Laravel/Flysystem com estas variáveis de runtime. A
variável `MEDIA_DISK` controla exclusivamente mídias e pode permanecer em
`public` localmente ou ser `s3` em produção:

```dotenv
FILESYSTEM_DISK=local
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=<secret>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=caldas-gestao-media
AWS_ENDPOINT=http://minio-d9nmgvtxdsppncjb1jl4lv0x:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

O endpoint interno serve para o backend. URLs destinadas ao navegador devem
usar um endpoint público HTTPS do storage ou uma rota/proxy da própria
aplicação; nunca devem apontar para hostname interno do Docker.

### 3.2 Bucket e prefixos

Manter o bucket privado e separar objetos por tenant:

```text
{tenant_id}/services/{service_id}/{random}.{extension}
{tenant_id}/products/{product_id}/{random}.{extension}
{tenant_id}/professionals/{professional_id}/{random}.{extension}
{tenant_id}/online-booking/{unit_id}/cover/{random}.{extension}
{tenant_id}/online-booking/{unit_id}/gallery/{random}.{extension}
{tenant_id}/units/{unit_id}/{random}.{extension}
```

O banco armazena somente o caminho relativo, nunca credenciais, URL interna
ou URL completa do storage.

### 3.3 Segurança

- Criar uma access key exclusiva para o Caldas Gestão, sem usar a root key em
  runtime.
- Permitir somente operações necessárias no bucket `caldas-gestao-media`.
- Manter o bucket privado e usar URLs temporárias quando a mídia não for
  pública.
- Validar MIME real, extensão permitida e tamanho máximo no Form Request.
- Nunca aceitar caminho de objeto vindo livremente do cliente.
- Derivar tenant e entidade a partir do contexto autorizado no backend.
- Não registrar secrets, URLs assinadas ou payloads completos nos logs.
- Rotacionar a access key com período de transição e rollback documentado.

## 4. Sprints

## Sprint 1 — Adapter, configuração e contrato de storage

### Entregas

- Aprovar e instalar `league/flysystem-aws-s3-v3` na versão compatível com o
  Laravel 13/Flysystem instalado.
- Confirmar `config/filesystems.php` e o disk `s3` com endpoint, path-style e
  URL configuráveis.
- Alterar `.env.example` com variáveis sem valores reais.
- Criar um serviço pequeno para centralizar nomes de diretório e geração de
  caminhos por tenant.
- Remover dependência de `Storage::disk('public')` nos fluxos de produção e
  usar o disk configurado, preferencialmente `Storage::disk(config(...))`.
- Manter `public` disponível para desenvolvimento local, se necessário, por
  meio de configuração explícita de ambiente.

### Critérios de aceite

- Composer instala sem conflito.
- `php artisan config:show filesystems` mostra o disk S3 corretamente quando
  executado com as variáveis de produção.
- O código não contém credenciais reais.
- Testes existentes continuam passando com `Storage::fake`.

## Sprint 2 — Access key de menor privilégio e bucket

### Entregas

- Criar uma policy/access key exclusiva para a aplicação.
- Conceder somente `ListBucket` restrito ao bucket e
  `GetObject`, `PutObject`, `DeleteObject` nos objetos do bucket.
- Confirmar que o bucket `caldas-gestao-media` permanece privado.
- Criar lifecycle policy para limpeza de uploads temporários e multipart
  incompletos, caso o volume justificar.
- Documentar rotação da chave e recuperação emergencial.
- Manter as variáveis sincronizadas em web, worker e scheduler no Coolify.

### Critérios de aceite

- Uma operação de leitura/gravação com a chave da aplicação funciona.
- A chave não consegue administrar usuários, buckets ou configurações globais
  do MinIO.
- O resultado da auditoria não expõe valores secretos.

## Sprint 3 — Abstração de caminhos e isolamento multi-tenant

### Entregas

- Criar um `TenantMediaPath`/serviço equivalente seguindo as convenções do
  projeto.
- Padronizar os domínios `services`, `products`, `professionals`, `units` e
  `online-booking`.
- Garantir que o `tenant_id` usado no caminho venha do `TenantContext`, da
  entidade autorizada ou do fluxo de onboarding, nunca de input confiado.
- Impedir que update/delete de uma entidade opere em caminho pertencente a
  outro tenant.
- Garantir nomes aleatórios e extensão derivada do conteúdo validado.
- Definir política para arquivos antigos quando a entidade for excluída.

### Critérios de aceite

- Dois tenants nunca compartilham prefixo de mídia.
- A tentativa de acesso cruzado retorna autorização negada ou objeto
  inexistente.
- Os caminhos permanecem independentes de domínio personalizado.

## Sprint 4 — Migração dos fluxos de upload

### Entregas

Migrar os fluxos existentes, sem duplicar regra entre controllers:

- serviços;
- produtos;
- profissionais/avatares;
- logo e branding da unidade/tenant;
- capa do agendamento público;
- galeria do agendamento público;
- qualquer outro upload encontrado por busca de `Storage`, `store`, `storeAs`
  e campos `*_path`.

Para cada fluxo:

1. validar arquivo no Form Request;
2. criar o registro ou obter o ID da entidade;
3. gravar no prefixo do tenant;
4. persistir somente o caminho relativo;
5. remover o arquivo anterior depois de a nova gravação e persistência
   estarem confirmadas;
6. tratar falha de storage sem deixar estado inconsistente;
7. usar transação/outbox ou rotina compensatória quando banco e storage
   precisarem ser coordenados.

### Critérios de aceite

- Nenhum upload de produção depende do disco local do container.
- Substituição remove ou marca corretamente o arquivo anterior.
- Exclusão remove o objeto sem apagar objetos de outro tenant.
- Falhas de MinIO retornam erro controlado e não confirmam operação parcial.

## Sprint 5 — URLs, frontend e site público

### Entregas

- Centralizar geração de URL em um serviço/accessor configurável.
- Definir quais imagens são públicas e quais exigem URL temporária.
- Para mídia pública, usar endpoint HTTPS público ou proxy controlado; nunca
  retornar endpoint Docker interno ao navegador.
- Atualizar accessors de `Service`, `Product`, `Professional`,
  `OnlineBookingSetting` e demais modelos.
- Verificar funcionamento nos hostnames:
  - `gestao.caldasindica.com`;
  - `gestao.romawear.com.br`;
  - `romawear.com.br`.
- Confirmar que URLs de mídia não vazam o tenant incorreto e não dependem do
  hostname pelo qual o painel foi acessado.

### Critérios de aceite

- Upload aparece no painel após refresh.
- Imagens aparecem no site público.
- URL temporária expira conforme a política definida.
- Nenhuma página fica em branco quando uma mídia está ausente ou inválida.

## Sprint 6 — Migração dos arquivos existentes

### Entregas

- Inventariar caminhos existentes no banco e no filesystem local.
- Criar comando Artisan idempotente, por exemplo `media:migrate-to-s3`, com:
  - `--dry-run`;
  - filtros por tenant/tipo;
  - lote e limite;
  - relatório de sucesso/falha;
  - checkpoint para reexecução;
  - não sobrescrever objeto já validado sem `--force`.
- Copiar cada arquivo preservando o caminho lógico padronizado.
- Validar existência, tamanho e checksum após a cópia.
- Só mudar o driver padrão depois de o inventário obrigatório estar migrado.
- Manter origem local em quarentena até o período de confirmação e backup.

### Critérios de aceite

- Todos os caminhos referenciados no banco têm objeto correspondente no S3.
- Falhas são listadas e podem ser corrigidas sem repetir sucessos.
- O comando é seguro para executar duas vezes.
- Existe backup/rollback antes da remoção do filesystem antigo.

## Sprint 7 — Testes automatizados e observabilidade

### Entregas

- Adicionar testes Pest para:
  - upload válido;
  - MIME/tamanho inválido;
  - atualização e remoção do arquivo anterior;
  - isolamento de prefixo entre tenants;
  - autorização de leitura/delete;
  - URL pública/temporária;
  - falha do storage;
  - migração idempotente;
  - fallback quando a imagem não existe.
- Usar `Storage::fake('s3')` nos testes de aplicação.
- Criar smoke test de integração opcional contra MinIO real em CI/staging.
- Adicionar métricas/logs sem dados sensíveis para falhas de upload e latência.
- Criar alerta para falhas repetidas de storage e crescimento de bucket.

### Critérios de aceite

- Suíte crítica passa localmente e no GitHub Actions.
- O isolamento possui teste positivo e negativo.
- O smoke test real confirma put/get/delete no bucket de staging.

## Sprint 8 — Ativação em produção e operação

### Entregas

- Fazer deploy primeiro em staging ou janela controlada.
- Executar migrations e comando de migração de arquivos conforme runbook.
- Alterar `FILESYSTEM_DISK=s3` somente depois dos critérios das sprints 1–7.
- Atualizar web, worker e scheduler com a mesma imagem imutável.
- Limpar cache de configuração sem expor secrets.
- Testar upload, visualização, substituição e exclusão em Romawear.
- Registrar a tag da imagem e o estado do Coolify.
- Atualizar o runbook operacional e o rollback.

### Critérios de aceite

- `gestao.romawear.com.br` continua saudável em HTTPS.
- Upload real do tenant Romawear persiste no bucket correto.
- O site público carrega suas imagens.
- Web, worker e scheduler usam a mesma configuração e imagem.
- Nenhum arquivo novo é gravado no filesystem local efêmero.

## 5. Rollback

### Rollback de aplicação

1. Restaurar a imagem anterior por tag imutável no Coolify.
2. Restaurar `FILESYSTEM_DISK=local` somente se o código anterior exigir.
3. Não apagar objetos S3 durante rollback.
4. Reverter variáveis somente após confirmar o comportamento da versão
   anterior.

### Rollback de dados

- Nunca remover o bucket como parte de rollback de deploy.
- Manter os objetos S3 até a versão estável ser confirmada.
- Se uma cópia foi incompleta, repetir o comando idempotente de migração.
- Restaurar caminhos no banco apenas por migration/command auditável.

## 6. Checklist de produção

- [ ] Adapter S3 instalado e lockfile commitado.
- [ ] Bucket privado criado.
- [ ] Access key de menor privilégio criada.
- [ ] Secrets configurados em web, worker e scheduler.
- [ ] Endpoint público HTTPS definido para URLs de navegador.
- [ ] `FILESYSTEM_DISK=s3` habilitado somente após testes.
- [ ] Todos os uploads usam prefixo de tenant.
- [ ] Upload/delete cross-tenant testado e negado.
- [ ] Arquivos existentes migrados e validados.
- [ ] CI verde: Pint, Larastan, TypeScript, build e Pest.
- [ ] Smoke test de produção executado.
- [ ] Backup e rollback registrados.
- [ ] Runbook atualizado.

## 7. Pendências que exigem decisão

- Aprovar a instalação de `league/flysystem-aws-s3-v3`.
- Definir se as imagens serão servidas por domínio público do MinIO, CDN ou
  proxy Laravel.
- Definir validade das URLs temporárias para mídia privada.
- Definir janela de retenção dos arquivos locais após a migração.
- Definir política e periodicidade de rotação das credenciais.
