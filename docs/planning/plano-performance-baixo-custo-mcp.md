# Plano de performance e escala econômica para API, MCP e assistente

**Data:** 01/10/2026
**Status:** plano revisado; P0 incompleto — baseline local sintética parcial e harness endurecido, aguardando CI PostgreSQL 17, staging/custos e volume/SLO aprovados; implementação de otimizações ainda não iniciada nesta fase de performance
**Relaciona-se a:** [PRD/ADR da API, MCP e assistente](prd-adr-api-mcp-assistente-ia.md)

## 1. Resumo executivo

É viável rodar API e MCP multi-tenant com custo baixo. O caminho recomendado é manter um único monólito Laravel e um endpoint MCP compartilhado, com autorização e consultas limitadas pelo tenant/unidade. O trabalho pesado de geração de linguagem fica no Groq; o servidor Caldas valida permissões, consulta PostgreSQL e registra propostas. Não criar uma instalação ou processo MCP por barbearia, não introduzir microserviços e não adotar Octane, Redis ou workers extras antes de medir necessidade.

Os ganhos de maior prioridade são corrigir o runtime de produção, medir os endpoints, reduzir trabalho repetido de `setup/status`, evitar que chamadas ao Groq ocupem processos web por até 20 segundos e não decodificar imagens grandes dentro da memória da API. Escala horizontal só entra depois que cache, limites, sessões, filas e arquivos forem compartilhados.

Este plano não atesta o desempenho atual de produção: não foram executados benchmarks contra o servidor ativo. Números e critérios abaixo são hipóteses a validar em staging com PostgreSQL e carga representativa.

### Resultado da revisão do plano

- As seis sprints abaixo estão ordenadas por dependência: medir antes de otimizar, corrigir o runtime antes de aumentar concorrência, e só habilitar réplica depois de compartilhar estado.
- A execução não altera a allowlist de dados/operações, não habilita OAuth e não implica deploy em produção.
- P3 (envio assíncrono ao Groq) fica bloqueada até a decisão pendente no PRD sobre enviar somente o turno atual ou também histórico, e até a política/controles da conta Groq estarem confirmados. O teste de performance deve começar com provedor fake.
- P4 (upload MCP) fica bloqueada até definir a entidade de destino e confirmar que a operação correspondente cabe na allowlist autorizada. O armazenamento binário e a intenção de upload não autorizam por si só edição de qualquer conteúdo.
- P5 é condicional: se a medição mostrar que uma réplica atende às metas com menor custo, registrar a decisão de permanecer nela e encerrar sem implementar scale-out.
- A goal futura deve usar este plano como escopo; ela não deve reabrir os domínios de clientes, agenda, caixa/vendas ou financeiro sem autorização separada.

## 2. Escopo e limites

- Continuam valendo: acesso administrativo apenas; autorização sempre vinculada ao tenant/unidade; aprovação humana com Passkey para efetivar propostas.
- Escopo externo de leitura já aprovado: nomes de categorias/serviços/profissionais, preço, duração e indicadores agregados de setup. Clientes, contatos, notas, vendas, estoque e financeiro seguem proibidos sem decisão explícita separada.
- Escritas externas continuam propostas revisáveis, restritas à allowlist efetivamente aprovada no PRD. Não executar alterações diretamente a partir de texto da IA.
- Groq continua provedor remoto configurável. O conteúdo enviado ao provedor, histórico, timeout, token e custo precisam de limites e telemetria sem registrar prompts, respostas, segredos ou dados de clientes em log.
- Upload de imagens geradas pelo ChatGPT é tecnicamente possível, mas a transferência e processamento de arquivo ficam numa fase própria com autorização, URL de upload curta e privada, quota e validação. O plano não autoriza novos tipos de arquivo ou superfícies externas.
- OAuth do MCP permanece desligado por padrão até homologação independente e decisão já pendente no PRD.

## 3. Estado conhecido e pontos de atenção

Evidências observadas no checkout deste worktree; devem ser reconciliadas com o commit e as configurações implantadas antes de alterar produção:

1. **MCP e REST têm bons limites de domínio.** MCP usa um endpoint comum; grants e contexto são revalidados, limites autenticados são por identidade/IP, catálogo usa projeções explícitas, paginação simples e índices compostos existentes. Isto reduz dados transferidos e mantém uma arquitetura única para todos os tenants.
2. **O corpo do diagnóstico de `setup/status` tende a executar cerca de 8–11 consultas**, pois soma contagens e verificações de prontidão/booking. Esse número descreve somente o corpo do diagnóstico; o pipeline HTTP completo medido no baseline local ficou em aproximadamente 38 consultas por request. Cache curto por tenant/unidade pode ser o ganho mais barato, desde que todas as escritas relevantes invalidem a chave.
3. **Groq é síncrono hoje.** Uma requisição pode fazer até três rodadas de ferramentas e aguardar o provedor por até 20 segundos. Isso ocupa um processo web mesmo quando a CPU local está ociosa. `assistant_runs` existe no banco, mas não tem job ativo.
4. **O Dockerfile no worktree usa `php artisan serve` no runtime e executa migrations no boot.** Mudar para um runtime de produção com PHP-FPM/proxy e migration one-shot é prioridade. A versão PHP do Dockerfile deve ser alinhada à versão exigida pelo projeto/CI antes de release.
5. **Configurações de desenvolvimento não provam produção.** `.env.example` usa SQLite, cache em arquivo e fila síncrona; runbook de produção documenta PostgreSQL dedicado, sessões em banco, web/worker/scheduler separados e S3-compatible. Confirmar valores efetivos no Coolify sem imprimir segredos.
6. **Uploads são limitados a 5 MB no ADR**, mas o otimizador carrega o arquivo e decodifica imagem em memória; dimensões grandes podem consumir muitas vezes o tamanho comprimido. Para entrada externa, upload direto ao storage e thumbnail assíncrona reduzem a pressão na RAM da aplicação.
7. **Buscas `LIKE '%texto%'` e páginas com OFFSET** perdem eficiência em catálogos grandes. As consultas de propostas resolvem alguns nomes em loop. Deixar essas otimizações condicionadas a medições; primeiro preservar a desambiguação segura e limitar o volume.
8. **Mídia local é inadequada para containers efêmeros.** O runbook já exige disco S3-compatible privado em produção; a integração precisa validar isso antes de permitir imagens externas.

### Checkpoint P0 — inspeção somente de leitura

Coleta inicial e atualização em 01/10/2026 no host `vps-caldas`; revisão GitHub via API autenticada somente leitura. Não foram lidas variáveis de ambiente, secrets ou logs, e nenhuma mudança foi executada no Coolify:

| Evidência | Observação | Limite da evidência |
| --- | --- | --- |
| Host | Snapshot de 02/10/2026 10:56 UTC: 8 vCPU; RAM total 25,20 GB, disponível 20,52 GB; filesystem raiz 310,91 GB, livre 265,77 GB. | Host compartilhado com outros workloads; leitura instantânea, não é capacidade livre sustentada nem pico/histórico. |
| App web | Container atual `svurav3cvfovuz8adtv1fuwv-151320303000`, imagem `ghcr.io/murilloimparavel/caldas-gestao:production`; criado/reiniciado em 01/10/2026 15:13 UTC. `docker stats` às 10:56 UTC: RSS 102,2 MiB, CPU 7,92%. Sem limite de memória/CPU (`HostConfig.Memory=0`, `NanoCpus=0`). | A tag `production` é mutável. RSS/CPU é uma amostra, não benchmark de rota, pico ou orçamento reservado. A diferença de CPU entre snapshots curtos não é série comparável. |
| Imagem web | Image ID local `sha256:61bc70c5b87a0e507c1458f77ae565e8a125d0859f4052b93fe8fc9297a31605`; revisão OCI `dcc785319ec84869df199cf13084982c52ba3fae`, criada em 01/10/2026 15:12:52 UTC. `gh api` confirmou a mesma revisão no HEAD da branch `production`. | Revisão reconciliada com o código publicado. Digest de manifesto do registry não foi obtido; ainda falta a configuração desejada no Coolify. A tag continua mutável. |
| Runtime web | Container classificado como `artisan-serve`; PHP 8.5.11 CLI, `php-fpm` ausente. O processo efetivo usa `php artisan serve`; a imagem executa migrations no boot conforme inspeção anterior. Sem limite explícito de CPU/RAM. | Confirma que a produção está usando o servidor de desenvolvimento; o runbook prescreve migrations separadas na release e conexão runtime mínima. |
| Scheduler | Container `ni3lxtnuyk26jh3pwpfzbdiu-201031772012`, imagem `ghcr.io/murilloimparavel/caldas-gestao:sha-96d94f5` / revisão `96d94f556e2f88c1d0c31d6cccb25c9b479d6588`, iniciado em 14/09/2026. `docker stats` às 10:56 UTC: RSS 44,38 MiB, CPU 52,43%. Sem limites explícitos de CPU/RAM. | Diverge da revisão atual da aplicação web e do HEAD atual de `production`. A leitura alta isolada pode ser um ciclo curto do scheduler e não é p95, utilização média nem prova de saturação. |
| Worker | Nenhum container localizado para o UUID `nbhjuwa4qhs5brnwnbqg1ea2` documentado no runbook; atualizado nesta coleta. | Configuração desejada no Coolify não consultada; verificar se o worker foi removido, renomeado ou está parado. |
| PostgreSQL | Container `zj6ryjlxbvb237rncuxpxhuo`, `postgres:16-alpine`, versão `16.15`, `max_connections=100`, uma sessão no instante da coleta. `docker stats` às 10:56 UTC: RSS 43,35 MiB, CPU 8,55%. Sem limites explícitos de CPU/RAM. | Uma contagem agregada instantânea não mostra pico, origem das sessões, saturação ou histórico; CPU e memória amostradas uma vez não representam perfil de carga. Não foram lidas queries, conteúdo, volume nem métricas históricas. |
| Fluxo de deploy | `.github/workflows/deploy-coolify.yml` só atualiza a tag `production` e dispara deploy para a aplicação web. O workflow não atualiza worker nem scheduler. O runbook afirma que os três serviços devem usar a mesma tag imutável. | Há divergência documentada entre automação/runbook e estado real dos serviços. Não foi alterado workflow nem serviço. |
| Health público | `GET /up` retornou 200 em `gestao.caldasindica.com` (0,906 s) e `gestao.romawear.com.br` (0,766 s). | Uma amostra por hostname; mede caminho do health check pela rede, não p95 de API/MCP, carga ou consulta ao banco. |
| Runtime de imagem | A imagem implantada executa `artisan serve`, tem PHP CLI 8.5.11 e não possui PHP-FPM, coerente com o runtime observado no Dockerfile do worktree. | O runtime ativo foi confirmado; a configuração desejada pelo Coolify, limites reservados e política de start/release ainda precisam ser reconciliados antes de P1. |

**Conclusão parcial:** nos snapshots de 01 e 02/10/2026, a revisão OCI do web (`dcc7853`) coincide com o HEAD GitHub de `production`, mas o deploy usa a tag mutável `production`. O web executa `artisan serve`/migrations no boot; scheduler continua na revisão `96d94f5` e o worker documentado não foi localizado. O workflow de deploy alcança somente o web, contrariando o runbook que exige imagem uniforme. Web/scheduler/PostgreSQL estão sem limites explícitos de CPU/RAM; o host tem outros workloads e as métricas são instantâneas. As leituras isoladas de CPU variaram de 0,14% a 7,92% no web e de 0,18% a 52,43% no scheduler, sem duração suficiente para inferir média ou saturação. O PostgreSQL 16.15 permite 100 conexões e tinha uma sessão ativa nas duas consultas; não há histórico ou pico. A P0 segue aberta: falta configuração desejada e custo do Coolify, histórico de CPU/RAM e conexões, volume/SLO acordado e perfil contínuo sob carga. A API do Laravel Boost disponível nesta sessão aponta para SQLite local; o estado desejado do Coolify ainda não foi consultado.

**Atualização read-only (02/10/2026 10:56 UTC):** SSH autenticado ao host permitiu repetir `docker inspect`, `docker stats` e uma consulta agregada `pg_stat_activity` sem ler env, logs ou tabelas de negócio. Web/scheduler/PostgreSQL permaneceram ativos, com as mesmas imagens/revisões e sem limites CPU/RAM; PostgreSQL permaneceu em 16.15, `max_connections=100`, uma sessão observada. A consulta GitHub do workflow `.github/workflows/tests.yml` em `production` não encontrou o job `e2e-assistant`; o SHA do worktree também não tem run CI contendo esse job. Portanto o harness alterado localmente ainda não foi executado no runner Ubuntu/PostgreSQL 17. O servidor local existente em `127.0.0.1:8000` foi preservado e não usado como staging.

**Atualização read-only (03/10/2026 22:21 UTC):** novo SSH ao `vps-caldas` coletou apenas metadata de containers, `docker stats` e recursos do host, sem env, logs, conteúdo de banco ou secrets. O web atual é `svurav3cvfovuz8adtv1fuwv-150000248000`, usa a tag mutável `production`, revisão OCI `cc8e5e5702ed0a2e4ca935902f674de771e86bc6`, iniciou em 02/10/2026 15:00:23 UTC e estava healthy. Metadata de configuração mostra que o processo web executa `php artisan migrate --database=migration --force --no-interaction` e serve via `php artisan serve`; os limites Docker `HostConfig.Memory` e `NanoCpus` são 0. O contador de reinícios observado era 0; a política de restart não foi consultada. O `docker exec ps` não pôde ser usado porque o binário não está instalado; o comando foi obtido da metadata da imagem/container. O scheduler é `ni3lxtnuyk26jh3pwpfzbdiu-201031772012`, usa a revisão `96d94f5` (`96d94f556e2f88c1d0c31d6cccb25c9b479d6588`), iniciou em 14/09/2026 12:27 UTC e também não tem limites explícitos. Snapshot de `docker stats`: web 97,48 MiB RSS / 0,03% CPU; scheduler 44,38 MiB / 0,17% CPU. Snapshot do host: 8 vCPU, 25,20 GB RAM total / 19,51 GB disponível, raiz 310,91 GB / 263,75 GB disponível, load average 0,90 / 1,18 / 1,23. Esses valores são amostras instantâneas num host compartilhado; não são orçamento reservado, perfil de carga nem evidência para dimensionar ou alterar o runtime. Estado desejado do Coolify, chave de API ausente no ambiente autorizado, custo e política de migração/deploy ainda precisam ser obtidos em staging/por via autorizada antes de P1.

**Amostragem passiva adicional de `docker stats` (03/10/2026 22:41:25–22:42:39 UTC):** SSH somente leitura coletou 12 amostras, espaçadas aproximadamente 6–8 segundos devido à latência dos comandos. No web, a memória ficou geralmente em 97,5 MiB, com uma leitura de 102,4 MiB; CPU variou de 0,03% a 10,41%; PIDs geralmente 2, com uma leitura de 8. No scheduler, memória ficou estável em 44,39 MiB, CPU variou de 0,16% a 52,84% e PIDs permaneceram em 1. O denominador dos containers era 23,47 GiB. A coleta foi passiva: não houve geração de carga, leitura de env/logs/dados de banco ou alteração de configuração. São snapshots oportunísticos, não uma média/carga válida, pico representativo, atribuição de tráfego, capacidade ou SLO; os transientes de CPU/PIDs não foram explicados. P0 permanece incompleto.

**Checkpoint do harness P0 (03/10/2026):** as proteções do runner/finalizer e do seeder foram revistas e verificadas localmente. O caminho sintético requer opt-in explícito; a URL raiz aceita somente loopback. O arquivo de credenciais deve conter o marker `_fixture=caldas-p0-synthetic-v1` e pelo menos dois tenants sintéticos distintos. Arquivos e artefatos são validados como regulares, privados e do usuário atual, com proteção contra symlinks (`O_NOFOLLOW` quando aplicável); artifacts são criados exclusivamente para impedir sobrescrita, e o cliente HTTP não segue redirects. O contrato exige cinco warmups, pelo menos 100 amostras e contagens de status observadas exatamente compatíveis com as amostras esperadas. O seeder PHP só executa em `testing` com opt-in, limita PostgreSQL à CI/loopback, banco com sufixo `_e2e` ou `_test`, schema `app` e papel runtime de privilégio mínimo; SQLite em memória fica restrito à execução Pest. As variáveis de opt-in do harness estão limitadas ao job `e2e-assistant`, sem habilitá-lo no job de qualidade geral.

**Complemento após revisão independente do seeder:** o seeder rejeita `DB_URL` override e aceita somente IP literal de loopback para PostgreSQL. Exige que o schema `app` já exista com `USAGE`; o papel de runtime não pode ser dono do schema nem ter privilégio `CREATE`. O destino do arquivo de credenciais precisa ser filho direto do diretório temporário canônico, sem diretório pai que seja symlink, e a criação final é exclusiva, sem sobrescrever arquivo existente. A suíte Pest do seeder foi ampliada e passou com **10 testes e 69 assertions**; Pint e lint foram reportados como aprovados pelo implementador.

Verificações locais registradas: Node performance **34/34 testes**, Pest do seeder **10/10 testes (69 assertions)**, parse YAML do workflow e sintaxe válida nos **26 blocos bash**. O Pest emitiu aviso de que o sandbox bloqueou a gravação do histórico de testes; os testes concluíram sem falha. O serviço que já escuta na porta `127.0.0.1:8000` pertence a outro repositório e não foi usado nem interrompido. Essas verificações não equivalem a uma execução do GitHub Actions, staging comparável ou aprovação de baseline.

**Validação local adicional (03/10/2026):** ESLint global, Prettier e TypeScript passaram; o build Vite passou após execução com permissão restrita ao worktree. O Node performance continua em **34/34**. A suíte Pest completa passou com `php -d memory_limit=512M vendor/bin/pest --compact`: **742 testes, 714 passaram, 28 foram skipped e 7.001 assertions**; a suíte do seeder permanece em **10 testes / 69 assertions**. O teste focado de OAuth/PKCE passou com **13 testes** após corrigir uma asserção falsa positiva: `assertDontSee('auth_code_id')` falhava com `APP_DEBUG=true` porque a string aparecia no bundle/HTML; foi substituída pela verificação direta de zero linhas em `oauth_auth_codes` e `OAuthGrant`. Pint e lint foram reportados como aprovados pelo implementador. `composer ci:check` foi tentado, mas parou na etapa de build porque o sandbox bloqueou o tempfile usado pelo Vite; o build isolado passou depois com acesso restrito ao worktree, portanto não se considera `composer ci:check` completo. A primeira execução Pest completa no sandbox teve logs bloqueados; a repetição com permissão restrita ao worktree passou. O estado de produção/staging não foi alterado. O snapshot Graphify após a atualização registra **7.921 nós, 20.771 arestas e 545 comunidades**.

**Verificação PHP adicional (03/10/2026):** após corrigir uma checagem redundante no seeder, PHPStan 2.2.9 passou com `memory_limit=512M` no host; Pest focado do seeder passou com **10 testes / 69 assertions**, e Pint e lint passaram. A execução local de PHPStan dentro do sandbox foi bloqueada por `EPERM`. `composer ci:check` não foi concluído: além do bloqueio local de tempfile/build já descrito, a integração parou sob o limite local de PHP `memory_limit=128M`. Portanto, não atribuir a essa tentativa o status de validação completa do CI.

**Atualização read-only desta sessão:** o worktree está em detached HEAD `76d1003fa7b653be61bf59ef99e7b80b5c01efe0` e contém mudanças amplas na árvore de trabalho. `gh run list --commit 76d1003fa7b653be61bf59ef99e7b80b5c01efe0 --workflow tests.yml` encontrou somente o run `36464443456`, de 28/09/2026 na branch `main`. `gh run view 36464443456 --json jobs` confirmou um único job, `Validar aplicação`, bem-sucedido entre 18:20 e 18:24 UTC, sem passos P0/E2E. Isso é evidência do workflow base genérico; não houve execução do branch/worktree atual, job `e2e-assistant` ou artifact P0. `COOLIFY_API_KEY_ROOT` estava ausente do ambiente autorizado; por isso não foi feita consulta à API Coolify nem leitura/exibição de segredos. O estado desejado do Coolify, custo e execução de staging permanecem indisponíveis nesta sessão. Nenhum push ou publicação foi feito.

**P0 permanece INCOMPLETO.** Ainda falta uma execução real do harness P0 no GitHub Actions com PostgreSQL 17 para este commit; não houve publicação/push do worktree nem execução do harness P0. A consulta read-only ao GitHub confirmou somente CI genérica do SHA base. Não houve push da árvore ampla/detached nem deploy. Também faltam staging, baseline comparável, configuração/custos desejados do Coolify e definição aprovada de volume concorrente e metas/SLO. Não marcar o baseline como aprovado nem considerar concluída a goal com base apenas nas verificações locais do harness.

### Checkpoint P0 — ambiente local sintético (não comparável a staging)

**Atualização de validação (01/10/2026):** além do smoke exploratório registrado na tabela abaixo, o runner usado pelo job de CI foi executado localmente com duas tenants sintéticas, cinco warmups e 100 amostras por rota. O resultado reproduzível está na seção [Resultado validado — runner local sintético](#resultado-validado--runner-local-sintético). Essa execução local valida o harness, mas não é uma execução de CI nem staging comparável; ela complementa, mas não substitui, o job PostgreSQL 17 do GitHub nem o perfil de carga definido após confirmar capacidade e volume de uso.

**Auditoria de cobertura (01/10/2026):** o host tem PHP 8.5.9, Node 26.3.1 e PostgreSQL 18.4, mas não tem PostgreSQL 17, Docker CLI ou `act`; o workflow usa PostgreSQL 17 e executa via GitHub Actions. Não foi disparado workflow remoto nesta auditoria. O artifact local mede cinco GETs e `POST /api/v1/operations`, com status, bytes, latência e métricas SQL; agrega as duas tenants e mantém sub-resumos pelos aliases sintéticos `tenant_a` e `tenant_b`. A fixture emite a credencial de leitura existente separadamente da credencial `operations:propose`, que é usada apenas na rota de proposta. As propostas são não confirmadas e não efetivam alterações. CPU/RSS/conexões sob carga, throughput concorrente, filas, custo, erros distribuídos e baseline CI continuam não homologados. Não existe endpoint de upload externo no contrato atual, e P4 permanece bloqueada pela definição de destino/entidade.

Executado em 01/10/2026 num ambiente local sintético com PostgreSQL temporário isolado, usando o papel de runtime com privilégio mínimo, migrations da aplicação e somente fixtures sintéticas. Esse ambiente local não é staging comparável. Ele tinha dois tenants/unidades: A com 100 categorias, 500 serviços e 50 profissionais; B com 8 categorias, 20 serviços e 4 profissionais. Os tokens temporários tinham apenas `context:read`, `catalog:read` e `setup:read`; OAuth/Groq externos ficaram desligados. Os arquivos temporários de credenciais e chaves permaneceram fora do repositório.

| Rota | Tenant A p50 / máximo observado (8 amostras) | Tenant B p50 / máximo observado (8 amostras) | Chamadas SQL agregadas no papel runtime por 8 requests | Bytes médios A / B |
| --- | ---: | ---: | ---: | ---: |
| `GET /api/v1/context` | 20,24 / 21,27 ms | 19,65 / 29,04 ms | 281 | 147 / 147 |
| `GET /api/v1/categories` | 20,34 / 21,03 ms | 20,44 / 21,37 ms | 289 | 606 / 291 |
| `GET /api/v1/services` (página 1) | 20,81 / 21,94 ms | 20,86 / 22,64 ms | 289 | 6.967 / 1.468 |
| `GET /api/v1/professionals` | 20,03 / 21,01 ms | 20,35 / 21,13 ms | 289 | 686 / 195 |
| `GET /api/v1/setup/status` | 22,99 / 28,48 ms | 23,01 / 25,76 ms | 337 | 475 / 473 |

Um smoke de concorrência de 10 leituras de serviços, com 5 clientes simultâneos, retornou 200 em todas; tempo total 241 ms, throughput observado 41,48 req/s, p50 109,11 ms e máximo de amostra 127,76 ms. É apenas teste exploratório contra `php artisan serve` local, dentro do ambiente sintético, e não representa staging comparável. Não inferir capacidade ou SLO desses números.

**Validação de isolamento:** as respostas de categorias, serviços e profissionais de cada token continham somente o namespace sintético do próprio tenant; contagens observadas foram 100/500/50 para A e 8/20/4 para B. A paginação deliberadamente devolveu até 100 serviços por página para A, não o total. Não foram impressos corpos completos nem tokens.

**Limites:** máquina de desenvolvimento macOS, PHP CLI com servidor embutido, PostgreSQL 18.4, oito amostras sequenciais por rota e um único perfil de concorrência. A produção usa PostgreSQL 16 e ainda não teve runtime reconciliado. Máximo de oito amostras não é p95/p99 confiável; RSS, CPU, conexões máximas, pool, erros sob carga progressiva, `operations/propose`, Groq e upload ainda não foram medidos. Os agregados indicam aproximadamente 281–337 chamadas SQL por oito requests (cerca de 35–42 por request), mas são deltas de `pg_stat_statements` por papel e janela, não uma captura identificada por requisição; podem incluir queries de autenticação, tenant context, policy, throttle/cache e atualização de uso. A leitura de código sugere que validação do token/credencial e elegibilidade administrativa compõem boa parte do custo fixo; `setup/status` acrescenta cerca de seis queries ao fluxo comum. A estimativa anterior de 8–11 queries descrevia o corpo do diagnóstico, não todo o pipeline HTTP. Confirmar a atribuição por request antes de otimizar e preservar todos os controles de autorização. Uma falha inicial de 500 foi causada pela chave Passport sintética não configurada no processo local; após apontar as chaves temporárias explicitamente, as rotas amostradas retornaram 200. Nenhuma configuração do repositório ou de produção foi alterada.

## 4. Arquitetura-alvo por estágio

### Estágio A — piloto de uma instância

- Uma imagem Laravel/web e uma réplica, por trás do proxy do Coolify.
- PHP-FPM com `pm=ondemand` e poucos processos; configurar `pm.max_children` a partir do RSS de pico medido por processo e do limite do container, reservando memória para sistema, OPcache, extensões e proxy. Não copiar valores genéricos sem medição.
- PostgreSQL dedicado conforme o runbook atual. Preservar conexão runtime de privilégio mínimo e conexão separada para migrations. Vigiar conexões disponíveis e pooler.
- Cache em banco pode servir para quota/limites compartilhados com uma réplica; cache em arquivo só é aceitável onde não controla segurança ou coordenação e enquanto existir uma instância persistente.
- Fila database com um worker apenas quando o assistente ou mídia passar a usar jobs. Enquanto não houver tarefa assíncrona, evitar processo ocioso adicional.
- S3-compatible privado para mídias; assets estáticos versionados no proxy/CDN.

### Estágio B — mais tráfego sem complexidade excessiva

- Tornar turnos Groq e transformação de imagem jobs idempotentes, com timeout, retry limitado, estado persistido mínimo e polling com intervalo progressivo. Resposta HTTP curta `202 Accepted`; nunca manter PHP-FPM preso ao tempo do provedor.
- Uma fila database e um worker pequeno são suficientes inicialmente. Reiniciar o worker no deploy e limitar sua vida (`--max-time` e/ou `--max-jobs`) para liberar memória. Migrar a fila para Redis/serviço gerenciado apenas se medir atraso/contenção na fila ou pressão relevante no PostgreSQL.
- Cachear `setup/status` por 15–30 segundos sob chave tenant+unidade; invalidar após qualquer mudança que afete o diagnóstico. Cache não é fonte de autorização e resposta nunca atravessa tenant/unidade.
- Usar paginação e campos de resposta limitados; adotar cursor pagination se dados e p95 demonstrarem custo crescente de páginas profundas.

### Estágio C — múltiplas réplicas

- Usar cache e locks compartilhados (Redis/Valkey ou serviço equivalente) para rate limits, locks e scheduler `onOneServer`; nunca depender de filesystem efêmero de um container.
- Sessões já devem permanecer centralizadas em banco ou store compartilhado. Arquivos vão para storage compartilhado. Validar que todos os nós usam a mesma chave/configuração.
- Colocar balanceador/proxy na frente e subir réplicas gradualmente. Definir workers separadamente do autoscaling web para que requisições lentas do Groq e mídia não esgotem o pool HTTP.
- Conferir máximo de conexões PostgreSQL por soma de réplicas e workers; usar pooler apenas quando a contagem observada exigir. Mais réplicas não devem levar a aumento cego de `max_connections`.

## 5. Regras práticas para RAM, CPU e custo

1. Reservar memória para SO/proxy, PHP-FPM, OPcache, extensões e picos; o limite de FPM será o menor entre o limite de memória do container e o orçamento definido após medir RSS. Registrar RSS p50/p95/pico por rota/carga.
2. Usar PHP-FPM sob carga concorrente real. O servidor embutido Artisan é somente para desenvolvimento. Não migrar para Octane: estado duradouro e consumo adicional não se justificam antes do profiling.
3. Manter instâncias pequenas e aumentar uma dimensão por vez: primeiro cache curto e consultas, depois um worker, depois RAM/CPU, depois réplica. Comparar custo mensal com aumento de p95 e erro antes/depois.
4. Fazer chamadas ao Groq com timeout explícito, no máximo de tool rounds configurado, limites de tokens/histórico por turno, limite de concorrência por tenant e custo mensal monitorado. Falha do Groq deve degradar a conversa sem bloquear leituras CRUD normais.
5. Mover conteúdo binário diretamente para storage com URL curta e assinada; limitar bytes e dimensões, verificar MIME real, assinatura/conteúdo, extensão e quota tenant-bound. Gerar variantes em worker com limite de dimensões; nunca carregar múltiplas cópias grandes no processo web.
6. Indexar com base em `EXPLAIN ANALYZE` e dados representativos. Índices já existentes cobrem vários filtros tenant/unidade. Não criar índice trigram ou duplicar índices sem query lenta documentada; cada índice aumenta custo de escrita e armazenamento.
7. Construir e publicar imagem no pipeline/build host, não competir por CPU/RAM com banco e aplicações em hora de pico. Deploy deve executar migrations como etapa de release com credencial própria, não em todo boot da réplica.
8. Coolify Cloud não inclui a infraestrutura do servidor. Se o controlador Coolify compartilhar host com aplicação e banco, reservar recursos para ele; a documentação recomenda pelo menos 2 CPU/2 GB para o próprio Coolify e alerta que builds/serviços concorrem por recursos. Preferir host remoto de workload quando o orçamento permitir ou validar a memória total real antes de co-localizar.

## 6. Plano de sprints

As sprints abaixo têm gates, não estimativas de duração. Manter o protocolo de execução do PRD: raiz orquestra/revisa/testa; implementações são divididas entre até três subagentes, `gpt-5.6-luna` por padrão, cada um com arquivos e contrato delimitados. Não pedir ao raiz para escrever código/configuração de implementação. Rodar a bateria TE2E do produto e testes de performance em cada etapa que altera fluxo ou runtime.

### Sprint P0 — inventário implantado e baseline

- [x] Coletar snapshot somente de leitura do host e containers; registrar imagem/revisão web, scheduler, limites de recursos e dois health checks públicos na tabela de checkpoint acima.
- [x] Reconciliar revisão OCI atual do web com o HEAD GitHub da branch `production`; inspecionar runtime/comando e tags reais do web, scheduler, worker e PostgreSQL no host sem ler env/secrets.
- [ ] Consultar estado desejado do Coolify e confirmar réplicas, configuração do worker/ scheduler, PostgreSQL/pooler, storage, limites operacionais e custos mensais.
- [x] Criar ambiente local sintético temporário sem PII/segredos; fixtures sintéticas em dois tenants/unidades com volumes de catálogo diferentes. Esse ambiente não é staging comparável.
- [x] Medir exploratoriamente `context`, catálogo e `setup/status`, e fazer um smoke de concorrência no ambiente local sintético; registrar valores e limites no checkpoint acima. Isso não satisfaz o aceite de baseline nem substitui staging comparável ou perfis de carga progressiva.
- [x] Fazer medição exploratória de `operations/propose` sintéticas não confirmadas e um turno do assistente com Groq fake, registrando latência/status e limites de atribuição na seção abaixo; o smoke de seis chamadas e o benchmark local de 100 amostras por rota não substituem CI/staging comparável nem fecham SLO.
- [x] Medir localmente cinco warmups e 100 amostras por rota; em `operations_propose`, 50 amostras por tenant sintético. O runner registra status e `Retry-After` sem conteúdo da resposta. A coleta inicial usou 1050 ms globalmente e poderia exceder `throttle:20,1` por owner; a cobertura HTTP confirmou limite 20/min por usuário, compartilhado entre tokens e independente entre usuários.
- [x] Repetir o benchmark local com intervalo de 3600 ms entre propostas alternadas (cerca de 8,3 chamadas/min por owner; cinco warmups incluídos). Artefato `/private/tmp/caldas-p0-proposal-baseline-fourth.json`: 100/100 propostas HTTP 202, 0 falhas e nenhuma resposta limitada com `Retry-After`; as cinco leituras tiveram 100/100 HTTP 200. A hipótese de throttle motivou o ritmo, mas não comprova a causa dos 16 HTTP 500 e 4 `network_error` da tentativa anterior, que continuam sem causa raiz porque seus logs não foram preservados.
- [x] Endurecer o sampler CI: validar linhas/campos/erros, árvore recursiva do PHP sem `/proc`, conexões Postgres e métricas do container; invalidar a baseline quando houver amostras incompletas; remover token de fixture e artefatos de trace Playwright do job.
- [x] Reforçar o harness P0 em revisão: verificar privilégios mínimos do papel PostgreSQL também no job `e2e-assistant`; adicionar testes Node nativos para agregação/validação do runner; aplicar tripwires locais provisórios de p95 (<1s leituras; <2s setup/proposta), explicitamente marcados no artefato como não aprovados como SLO.
- [x] Corrigir e auditar o sampler CI: renomear a variável AWK reservada `index` (smoke sintético pai/filhos soma RSS/CPU corretamente); exigir cadência mínima de consultas frescas de conexões Postgres proporcional à duração, rejeitando amostra sustentada só por valores carregados.
- [x] Extrair a validação/sumarização de recursos para módulo Node testável; cobrir métricas, linha inválida, status E2E/API, cadência Postgres, amostras insuficientes, baseline inválida, parser de status estrito e bytes com overflow. Validação local atual do módulo Node P0: 18/18; a execução no GitHub Actions com PostgreSQL 17 permanece pendente.
- [ ] Completar baseline de `context`, catálogo, `setup/status`, proposta e assistente com observação de CPU/RSS/conexões/erros/fila sob carga de staging; separar latência local e fake provider. Medição de upload depende da aprovação da entidade/destino e de endpoint autorizado.
- [ ] Rodar carga concorrente crescente até a meta de utilização real ser definida pelo produto; registrar p95, throughput, 429, 5xx, saturação FPM/DB e custo. Não escolher número de usuários virtuais sem inventário de uso esperado.
- [ ] Definir SLO provisório alinhado ao PRD atual: leitura simples p95 <1s e ação/diagnóstico de setup p95 <2s sob carga de piloto; turno IA <60s incluindo provedor, com feedback assíncrono. Registrar que são metas propostas, não baseline comprovada.

**Modelo econômico do Groq (referência de 01/10/2026; não é custo medido):** `config/assistant.php` usa `openai/gpt-oss-120b` como default de código, mas o `GROQ_MODEL` efetivo em produção não foi consultado. A tabela pública atual mostra US$ 0,15 por 1 milhão de tokens de entrada e US$ 0,60 por 1 milhão de saída para esse modelo; entrada em cache pode custar US$ 0,075/M quando houver hit elegível, mas não deve ser assumida no orçamento ([preços/modelo](https://console.groq.com/docs/model/openai/gpt-oss-120b), [prompt caching](https://console.groq.com/docs/prompt-caching)). Fórmula conservadora por turno: `((tokens_input_totais × 0,15) + (tokens_output_totais × 0,60)) / 1.000.000`, somando todos os requests Groq daquele turno; o serviço atual permite até três rounds, logo a contagem do turno não é igual à de uma chamada. Exemplo apenas ilustrativo: 1.000 tokens de entrada totais + 500 de saída totais custariam US$ 0,00045 por turno, ou US$ 4,50 em 10.000 turnos; isso não inclui créditos/impostos nem demonstra volume real. O código atual não persiste `usage`/tokens do payload do provedor, portanto não há custo real por turno disponível ainda.

**Privacidade Groq (documentação oficial consultada em 01/10/2026):** a documentação informa que inferência não retém Customer Data por padrão, mas entradas/saídas podem ser temporariamente retidas por até 30 dias para confiabilidade/abuso; todos os clientes podem habilitar Zero Data Retention nos controles da organização, e os dados de Customer Data retidos ficam em buckets GCP nos EUA ([Your Data in GroqCloud](https://console.groq.com/docs/your-data)). Essa política geral não confirma as configurações, elegibilidade, região contratual ou termos da conta usada pelo Caldas. Não enviar conteúdo real até confirmar isso e decidir explicitamente se o serviço enviará apenas o turno atual ou também histórico.

**Aceite:** baseline reproduzível das rotas prioritárias em staging, arquitetura Coolify conciliada, SLO e volume-alvo definidos com evidência; nenhum segredo/PII em artefato. O snapshot de host e health check sozinho não fecha este aceite.

**Gate de saída:** não alterar imagem, configuração de produção ou dados. As execuções locais demonstram que o harness de leituras e os fluxos sintéticos de proposta/Groq fake funcionam; ainda não fecham P0. Fechar o aceite somente após: (1) executar o job PostgreSQL 17 na CI; (2) obter, por fonte autorizada e sanitizada, configuração desejada e custo do Coolify, papel do worker, storage e limites de recursos; (3) definir volume concorrente e SLO do piloto; e (4) completar a observação dos recursos/erros sob carga no staging. Upload só entra nessa medição se a entidade de destino for aprovada. Até então, não afirmar capacidade ou metas atingidas.

**Amostragem de produção:** não executar carga concorrente ou teste de stress em produção. Todas as medições de throughput/p95/concorrência devem acontecer em staging isolado e com dados sintéticos; health checks públicos são observações individuais de disponibilidade apenas.

**Bloqueios externos registrados:** faltam a estimativa de barbearias/tenants no piloto e em 12 meses, usuários administrativos simultâneos e um resumo sanitizado da configuração desejada do Coolify (réplicas, limites de CPU/RAM, comandos web/worker/scheduler, cache/fila/storage e custo mensal). Solicitar somente nomes/valores operacionais; nunca solicitar segredos, tokens, dados de clientes ou conteúdo de produção. Se não houver resposta, continuar auditorias e testes locais independentes sem inventar esses valores; manter dimensionamento e alteração do runtime bloqueados.

**Tentativa de consulta read-only ao Coolify (01/10/2026):** a variável `COOLIFY_API_KEY_ROOT` não está disponível no ambiente autorizado desta tarefa; o GET do endpoint da aplicação respondeu HTTP 401. Não consultei arquivos `.env` nem tentei obter credenciais por outra origem. Portanto, o estado desejado atual, limites e custo não puderam ser reconciliados via API nesta sessão; permanece necessária uma fonte autorizada/sanitizada fornecida pelo usuário ou uma sessão com a credencial de leitura apropriada.

**Opções de avanço independente:** a CI é a única execução disponível que reproduz PostgreSQL 17 e ambiente Ubuntu; requer fluxo GitHub autorizado. Não instalar PostgreSQL/Docker apenas para simular a CI. Em ambiente local sintético temporário separado, sem tráfego/PII de produção, é possível medir proposta sintética não confirmada e latência do fake Groq, além de amostrar CPU/RSS/conexões durante uma carga definida. Isso não substitui staging comparável. Carga progressiva e conclusão do dimensionamento continuam condicionadas ao volume e SLO aprovados; não adicionar rota de upload apenas para benchmark.

**Tentativa inicial de medir proposta/Groq fake (01/10/2026):** a primeira execução não produziu resultados válidos porque o sandbox bloqueou o bind de porta loopback (`EPERM`); os serviços foram encerrados e os artefatos removidos. Após usar escalada restrita ao loopback e validar o readiness individualmente, a repetição abaixo foi concluída. Nenhum processo da aplicação que já estava aberto em `:8000`, banco externo, Coolify ou produção foi alterado.

**Medição exploratória sintética concluída (01/10/2026):** ambiente isolado em PostgreSQL 18.4, PHP 8.5.9/Laravel 13.26.1, `artisan serve` em `127.0.0.1:8003` e Groq fake em `127.0.0.1:8789`. O Groq fake `/health` e Laravel `/login` retornaram 200 antes da coleta. Seis propostas REST sequenciais sem confirmação retornaram 202, com 69 queries cada, tempo SQL de 22,38–25,25 ms e `Cache-Control: no-store, private`; HTTP total ficou entre 52,879 e 209,462 ms. A primeira chamada foi fria (209,462 ms); cinco chamadas quentes ficaram entre 52,879 e 57,851 ms, mediana quente aproximada de 53,575 ms. Um turno pela tela interna com Groq fake levou 321 ms ponta a ponta no navegador (POST 302, GET da conversa 200). As rotas web do chat não incluem os headers de contagem SQL, então não há contagem de queries atribuível ao turno. No banco temporário foram observadas sete propostas pendentes, zero linhas de serviço criadas e quatro mensagens do assistente; nenhuma proposta foi aprovada. Isso é evidência exploratória de fluxo e latência, não p95/SLO, capacidade sob concorrência, ou medição de RSS, CPU, conexões máximas, fila ou custo. O PostgreSQL, Laravel e Groq fake foram encerrados; as portas 8003, 8789 e 55455 ficaram sem listeners; credenciais/chaves/diretório temporário foram removidos. O artifact anterior `/private/tmp/caldas-p0-ci-sim/baseline.json` foi preservado.

### Sprint P1 — runtime e deploy leves

- [ ] Substituir `artisan serve` por PHP-FPM + proxy de produção suportado pelo ambiente; respeitar compatibilidade de versão PHP do projeto.
- [ ] Mover migration para tarefa de release one-shot com conexão administrativa própria; container web apenas inicia serviço já preparado.
- [ ] Ajustar limite de workers FPM, `pm.max_requests`, `memory_limit`, OPcache e limites do container com dados P0.
- [ ] Aplicar `php artisan optimize` na imagem/release; confirmar cache de config/rotas/views sem expor env em código e reiniciar workers no deploy.
- [ ] Adicionar health/readiness útil sem consultar indiscriminadamente dependências em cada request; testar SIGTERM e restart gracioso.
- [ ] Revisar isolamento entre web, worker e scheduler existentes; impedir que build/release concorra com produção.

**Aceite:** smoke e TE2E passam, zero migrations no boot normal, processo web sob limite, rollback para imagem imutável validado e nenhuma regressão de login/API/MCP.

**Gate de entrada:** confirmar o runtime/tag implantado, versão PHP do CI e topologia de deploy. Se `artisan serve` já não for o runtime real, atualizar o diagnóstico e não substituir um runtime saudável sem evidência.

### Sprint P2 — consultas, cache e limites por tenant

**Hipótese de perfilamento para `operations/propose`:** a observação exploratória contou 69 queries no request inteiro, incluindo bootstrap, autenticação e controller; ainda não atribui queries por camada. A leitura do fluxo (`RequireIntegrationCredential` → `TenantContext::forUser`/policy → `ProposedOperationService::revalidateContext`/`assertSourceContext`) mostra validações independentes de token, tenant/unidade e elegibilidade em mais de uma camada. Isso é defesa em profundidade e só pode ser otimizado após medir sua contribuição individual e demonstrar, com testes de revogação imediata, mudança de membership/assinatura e isolamento cross-tenant, que a mesma garantia permanece. Não reutilizar uma autorização antiga nem remover revalidação apenas para reduzir o contador.

**Candidato a otimização de `setup/status`:** na repetição do runner P0, essa rota executou 38 queries/request (p50/p95/p99) e acumulou 20,89 ms de SQL no p95; as leituras de categorias/serviços ficaram em 32 queries e 16,91–17,12 ms de SQL no p95. A diferença observada é seis queries no caminho total e cerca de 3,8–4,0 ms de SQL p95 contra essas rotas de comparação. A leitura de `IntegrationCatalogQuery::setupStatus` confirma três contagens/exists, uma consulta do site e eager loads de draft/publication; os headers não segregam esses componentes. Isso justifica avaliar cache curto, mas não é prova de ganho líquido: antes de implementá-lo, confirmar o cache store de produção, medir hit/miss no mesmo staging e enumerar invalidação para alterações de serviço, profissional, disponibilidade, unidade e booking. Nunca cachear elegibilidade, credencial, grant ou autorização; chave precisa incluir tenant+unidade, cache indisponível deve cair para consulta direta e a resposta externa continua `private, no-store`.

- [ ] Instrumentar query count/custo por request e separar middleware/autenticação/autorização do controller sem registrar payload sensível; confirmar o agregado P0 e revisar avaliações repetidas de elegibilidade administrativa sem reduzir as garantias de acesso.
- [ ] Implementar cache curto tenant+unidade para `setup/status`, com invalidação nos caminhos de escrita relacionados e fallback seguro em erro de cache.
- [ ] Confirmar limites de página, busca, body e concorrência; proteger páginas profundas e pesquisas caras. Migrar para cursor somente se P0/P2 demonstrar necessidade.
- [ ] Executar `EXPLAIN ANALYZE` nas consultas lentas em staging; criar índices apenas com justificativa e medir custo de escrita/migration.
- [ ] Resolver nomes de propostas em lote (`whereIn` + agrupamento) caso os perfis P0 indiquem muitas consultas, conservando falha fechada para ausentes/ambíguos.
- [ ] Validar limits/locks compartilhados de acordo com topologia: store atual para uma réplica; Redis/Valkey ou rate limit no gateway antes de múltiplas réplicas.

**Aceite:** metas P0 atingidas para leituras prioritárias sem ampliar campos ou permissões; regressões de isolamento tenant/unidade e limites negadas por Pest/TE2E.

**Gate de saída:** cache e limites não mudam a autorização; provar invalidação para cada operação que afeta setup e isolamento de chave em tenants/unidades diferentes. Caso cache não reduza latência/carga de modo mensurável, preferir não o manter.

### Sprint P3 — assistente Groq assíncrono e orçamento

- [ ] Ativar `AssistantRun` como execução idempotente, com um job por conversa/run, estado mínimo e checagem de ownership/contexto em toda leitura e retomada.
- [ ] Responder `202` e consultar estado via polling leve; reduzir a frequência quando a execução demora. Mostrar erro amigável e retry explícito.
- [ ] Usar uma fila database e um worker de baixa concorrência inicialmente; controlar timeout, tries, `--max-time`/`--max-jobs`, tamanho de payload e memória. Escalar para Redis só com evidência de backlog/contenção.
- [ ] Definir orçamento por tenant/usuário: limite de chamadas e concorrência, tokens de entrada/saída, teto de histórico e aviso/alerta de custo. Aplicar 429 e backoff quando Groq limitar ou exceder cota.
- [ ] Separar métricas de latência local e externa Groq; redactar mensagens/texto livre antes de qualquer telemetria e respeitar retenção aprovada.
- [ ] Testar timeout, indisponibilidade, rate limit, duplicidade, cancelamento/expiração e concorrência entre tenants; proposta continua exigindo revisão/Passkey.

**Aceite:** nenhuma chamada externa mantém processo web ocupado; replays não duplicam proposta; tenant não lê run de outro tenant; custo e limite configuráveis.

**Gate de entrada:** decisão registrada no PRD sobre payload outbound Groq (turno atual vs. histórico), retenção e configuração da conta do provedor. Até lá, limitar trabalho a medições com fake, contratos e threat model; não ativar async real que persista/reenvie conteúdo.

### Sprint P4 — upload e mídia para ChatGPT/MCP

- [ ] Projetar intent de upload autenticado, tenant/unidade-bound, curto e de uso único, com finalidade e entidade de destino autorizadas.
- [ ] Fazer upload direto ao bucket privado por URL pré-assinada; backend finaliza e valida tamanho real, MIME, dimensões, hash, quota e vínculo do objeto.
- [ ] Processar thumbnail/normalização em job com limite de dimensões e consumo; não baixar o arquivo para a memória do PHP web.
- [ ] Armazenar referência interna e metadados mínimos; nunca persistir URL assinada, bytes ou segredo no histórico Groq/MCP/log.
- [ ] TE2E cobre sucesso, expiração, reuso, outro tenant, arquivo adulterado, excesso de tamanho/dimensão e falha do worker.

**Aceite:** arquivo privado e isolado, links expiram, telemetria não contém URL/token, recurso respeita aprovação humana e allowlist.

**Gate de entrada:** aprovação explícita do fluxo e entidade do sistema que receberá a imagem. Começar com protótipo privado sem ativar tool externa; nenhum path arbitrário pode ser passado pelo MCP.

### Sprint P5 — prontidão para réplicas e escala horizontal

- [ ] Antes da 2ª réplica, migrar limites, cache, locks e scheduler para backend compartilhado e sessões/arquivos para stores compartilhados.
- [ ] Calcular orçamento de conexões Postgres a partir de réplicas × `pm.max_children` + worker/scheduler + tarefas operacionais; ajustar pooler com evidência.
- [ ] Testar duas réplicas e worker separado com tenant isolation, rate limit global, revogação imediata, locks e execução única.
- [ ] Configurar autoscaling manual/automático com gatilhos em saturação FPM, CPU, latência e erro, não apenas tráfego instantâneo. Começar com limite conservador para proteger banco.
- [ ] Executar load test progressivo e TE2E em ambiente de staging; medir p95, error rate, fila, conexões, memória e custo total por faixa de carga.
- [ ] Documentar decisão: permanecer uma réplica se SLO estiver atendido; ampliar verticalmente primeiro quando for mais barato; usar réplicas quando CPU/FPM forem o gargalo e o banco/cache compartilhados estiverem folgados.

**Aceite:** segunda instância pode entrar/sair sem perda de sessão, inconsistência de quota, duplicação de job ou fuga cross-tenant; rollback testado.

**Gate de entrada:** somente prosseguir se a medição P0/P2/P3 justificar mais capacidade e o custo total for melhor que aumentar verticalmente. Caso contrário, registrar “não necessário” com métricas e fechar esta sprint sem adicionar serviços.

## 7. Divisão paralela sugerida

Em cada sprint, limitar a três trilhas que não editem os mesmos arquivos:

| Trilha | Responsável sugerido | Escopo |
| --- | --- | --- |
| Runtime/deploy | Subagente A | Docker/runtime/health/release/worker; não alterar contratos MCP nem schema de domínio. |
| Dados e limites | Subagente B | Consultas, cache e índices aprovados; não editar Docker/UI de assistente. |
| Assistente/mídia | Subagente C | `AssistantRun`, jobs, storage/upload; não alterar middleware OAuth nem políticas externas. |
| Orquestração | Agente raiz | Congelar interfaces, revisar segurança, rodar CI/TE2E/load, reconciliar branches e atualizar PRD. |

Na Sprint P0, usar subagentes apenas para auditoria independente e reprodutível. Toda trilha consulta regras locais e skills Laravel/Inertia/Pest adequadas antes de tocar seus paths.

## 8. Métricas e gates contínuos

- HTTP/MCP: p50/p95/p99 por rota e status; tamanho de resposta; 401/403/429/5xx; sem parâmetros, tokens ou conteúdo nos labels.
- PHP-FPM/container: processos ativos/ociosos, fila FPM, RSS p50/p95/pico, CPU e reinícios/OOM.
- Banco: conexões ativas/máximas, duração/contagem de queries por endpoint, locks, pooler e storage. SQL/PII só em ambiente controlado e redigido.
- Jobs/Groq: fila e idade mais antiga, duração local vs provedor, retries/timeouts, tokens/custo agregado por tenant e falha de provider.
- Mídia: bytes/dimensões, duração de upload e job, falhas de validação, quota e storage por tenant.
- CI/CD: gate TE2E da allowlist autorizada e dos fluxos admin/colaborador; check de compatibilidade de migration/PostgreSQL e teste de duas réplicas antes de habilitar scale-out.
- SLOs são revisados depois de P0. Mudança de meta requer motivo e medição comparável; não marcar como atingido só porque os testes funcionais passaram.

### Revisão a cada sprint

- [ ] Mudança proposta aponta para uma medição do baseline e tem hipótese mensurável de latência, memória, carga do banco ou custo.
- [ ] O escopo de dados/capabilities permanece igual ao aprovado; limites e autorização foram testados em mais de um tenant/unidade e com colaborador.
- [ ] Existe plano de rollback simples, e mudança de schema segue expand/contract.
- [ ] Pest e os gates TE2E aplicáveis passaram; carga/perfil foi executado no mesmo ambiente comparável e a medição depois foi registrada.
- [ ] Só se inicia sprint seguinte se o gate anterior foi atingido ou se a limitação externa foi documentada sem alegar sucesso.
- [ ] Após cada sprint, registrar arquivos/serviços tocados, evidências, custo observado, pendências e decisão de continuar/parar.

### Checkpoint P0 — harness sintético de API

O harness isolado do job CI `e2e-assistant` foi preparado para rodar com PostgreSQL 17, duas tenants/unidades sintéticas, credenciais de leitura e uma credencial separada somente para `operations:propose`, medição de latência/status/tamanho e métricas de contagem/tempo de queries por request. O runner separa warmup, exige amostra mínima de 100 requisições por rota, inclui `POST /api/v1/operations` com status esperado `202`, body sintético de `service.create` e chave de idempotência exclusiva por amostra, falha diante de status inesperado, métricas SQL ausentes ou duplicidade de chave e preserva o JSON agregado para upload mesmo quando o gate falha. O artifact não contém token, body, chave de idempotência ou IDs; os sub-resumos usam somente os aliases `tenant_a` e `tenant_b`. A configuração do workflow não significa que a execução remota já ocorreu.

As verificações estáticas, YAML/bash/Node do workflow/runner, ESLint direcionado de `tests/performance/*.mjs` e os testes Node nativos do runner e do resumo de recursos (18/18) e os testes Pest focados passaram localmente; a validação local de YAML/bash registrou 26/26 etapas. Isso inclui a separação das credenciais read-only e `operations:propose`, e os privilégios mínimos do papel PostgreSQL no job E2E. Essas verificações locais validam o código e a configuração do harness, mas não substituem a execução no GitHub Actions com Ubuntu e PostgreSQL 17. O passo REST do workflow roda com `if: always()`, e o finalizador também roda sempre, lê o status persistido da API e rejeita o resumo quando o status está ausente ou falha. Os parsers dos headers de contagem e tempo de query aceitam somente valores estritos, completos, não negativos e seguros; timestamps de recursos precisam crescer e respeitar pelo menos 90% do intervalo configurado; o resumo rejeita uma rota esperada ausente no artifact. O runner agora valida número exato de amostras, respostas, métricas, chaves de idempotência e limites p95 provisórios. Esses limites aparecem no artefato com `approved_slo: false`; são somente tripwires para regressão local, não SLO aprovado. A auditoria também corrigiu um erro que invalidava todas as amostras de RSS/CPU no CI: `index` era usado como variável AWK apesar de ser função interna; o smoke com árvore sintética pai/filhos passou (`6000 KB`, `6.50%`, processo não relacionado excluído). O finalizador agora exige consultas frescas de conexão Postgres equivalentes a pelo menos 75% do mínimo esperado; fixture com 15 consultas passou e apenas uma falhou. O módulo de resumo de recursos recebeu testes para métricas, linha inválida, status E2E/API, cadência Postgres, amostras incompletas, baseline inválida, status não numérico, overflow de bytes, timestamps não monotônicos ou abaixo do intervalo e rota de baseline ausente; todos 18 testes Node passaram. A suíte Pest completa passou em SQLite em memória: 733 testes descobertos, 705 aprovados, 28 ignorados, 6.951 assertions. O teste `TrustProxiesTest` foi corrigido para configurar/restaurar `TRUSTED_PROXIES` em `$_ENV`, `$_SERVER` e `getenv()`, pois alterar somente `putenv()` não substitui o valor vazio já carregado pelo Dotenv imutável. A tentativa do navegador local não foi concluída: `initdb` do PostgreSQL 18 falhou por limite de shared memory do host; a porta `:8000` não foi usada. A consulta read-only ao GitHub encontrou o run `36464443456` (`CI · Testes e qualidade`) no SHA `76d1003fa7b653be61bf59ef99e7b80b5c01efe0`, concluído com sucesso em 28/09/2026; os jobs desse run mostram somente `Validar aplicação`, sem `e2e-assistant` ou artifact do baseline P0. O workflow/harness P0 alterado neste worktree ainda não foi executado no GitHub, portanto não há baseline CI P0 reproduzível de p50/p95/p99 nem aceite declarado. O `graphify update .` foi executado e reextraiu o checkout, registrando 7.896 nós, 20.704 arestas e 551 comunidades; regenerou `graph.json`, `graph.html` e `GRAPH_REPORT.md` e salvou snapshot em `graphify-out/2026-10-02/`. Permanecem os avisos de SQL opcional não instalado e de erro parcial em `resources/js/types/index.ts`; isso não altera o gate P0 e `graphify label` não foi executado.

Cada execução do runner faz 5 warmups e 100 amostras por rota. Para `operations_propose`, isso representa 105 chamadas `POST /api/v1/operations`; todas usam payload sintético e chave de idempotência exclusiva e criam somente propostas pendentes no banco descartável do job, sem confirmação, Passkey ou criação/edição efetiva de serviços. O resumo global e os sub-resumos por `tenant_a`/`tenant_b` são agregados sem identificadores.

### Resultado validado — runner local sintético

Esta execução local é a evidência de harness mais forte disponível nesta revisão; não confundir com execução do job CI nem com o smoke exploratório de oito amostras/concorrência registrado no checkpoint de staging.

Em 01/10/2026, o runner foi executado localmente com `APP_ENV=testing`, PostgreSQL 18.4 temporário em loopback, PHP 8.5.9 e `artisan serve` em `:8001`. Foram usados os dois tenants do `P0ApiPerformanceSeeder`: A com 20 categorias, 100 serviços e 20 profissionais; B com 8 categorias, 40 serviços e 8 profissionais. Cada rota teve 5 warmups e 100 amostras, intervalo de 1050 ms, `failure_count=0` e status 200 em todas as 100 amostras.

Esse resultado registrado foi produzido antes da extensão da rota `operations_propose`; portanto, a tabela abaixo cobre apenas as cinco leituras. Depois dele, um smoke local isolado com PostgreSQL 18 temporário confirmou as leituras HTTP 200, `POST /api/v1/operations` HTTP 202 e a presença das métricas SQL, usando duas tenants sintéticas. O smoke da proposta registrou 69 queries e, antes do benchmark completo, o banco descartável tinha 160 serviços e uma proposta pendente. O benchmark completo foi interrompido antes do fim: não há artifact final, contagens finais nem pós-contagem de propostas/serviços, e esse smoke não deve ser tratado como baseline. O scratch e os segredos sintéticos foram removidos ao encerrar a execução; as 105 chamadas por execução ainda aguardam o job CI com PostgreSQL 17.

| Rota | Latência p50 / p95 / p99 (ms) | SQL count | SQL time p50 / p95 / p99 (ms) | Bytes médios |
| --- | ---: | ---: | ---: | ---: |
| `context` | 55.789 / 60.613 / 61.147 | 31 | 14.10 / 15.30 / 16.35 | 147 |
| `categories` | 58.396 / 62.845 / 73.249 | 32 | 14.68 / 15.95 / 17.38 | 543 |
| `services` | 59.218 / 63.963 / 64.664 | 32 | 14.97 / 16.49 / 16.63 | 1646 |
| `professionals` | 59.805 / 63.351 / 64.705 | 32 | 15.34 / 16.44 / 17.33 | 585 |
| `setup_status` | 62.232 / 66.677 / 67.338 | 38 | 18.35 / 19.54 / 19.95 | 474.5 |

Artefato agregado: `/private/tmp/caldas-p0-ci-sim/baseline.json`. Esta execução não é comparável a produção e não estabelece SLO: o snapshot pós-run mediu RSS, CPU e conexões, mas não sob carga; RSS p95/pico, CPU durante o teste, conexões máximas, concorrência, carga, proposal, Groq e imagem continuam não medidos. O run genérico do GitHub no SHA então publicado não executou o job `e2e-assistant` nem gerou o artifact P0 com PostgreSQL 17; o aceite P0 permanece aberto.

Snapshot pós-coleta, imediatamente depois do run: `php artisan serve` PID 72037 com RSS 27088 KB e CPU 0.0%; PostgreSQL PID 71463 com RSS 9248 KB e CPU 0.0%; 1 conexão ativa no instante da consulta agregada. Esse snapshot é posterior à carga e não representa pico, RSS p95 ou máximo de conexões sob carga; não permite inferir capacidade ou SLO.

### Benchmark completo local de proposta (01/10/2026)

O benchmark final foi executado com PostgreSQL 18 temporário em loopback, PHP 8.5.9 e `artisan serve`, usando seis rotas, cinco warmups e 100 amostras por rota, com 50 amostras por tenant sintética. O artifact agregado foi `/private/tmp/caldas-p0-proposal-baseline-third.json`, modo `0600`. Houve `0 failures`: cada rota de leitura teve 100 respostas HTTP 200 e `operations_propose` teve 100 respostas HTTP 202. O payload da proposta foi sintético; o artifact não contém IDs, tokens ou chaves de idempotência.

| Rota | Latência global p50 / p95 / p99 (ms) | SQL count | SQL time p95 (ms) | Observação de status |
| --- | ---: | ---: | ---: | ---: |
| `context` | 60.464 / 67.801 / 102.729 | 31 | 18.91 | 100 × 200 |
| `categories` | 58.685 / 68.089 / 94.981 | 32 | 18.37 | 100 × 200 |
| `services` | 57.701 / 67.471 / 120.519 | 32 | 16.66 | 100 × 200 |
| `professionals` | 62.917 / 68.469 / 69.796 | 32 | 16.52 | 100 × 200 |
| `setup_status` | 66.547 / 71.845 / 72.737 | 38 | 20.01 | 100 × 200 |
| `operations_propose` | 81.783 / 88.226 / 89.729 | 69 | 29.40 | 100 × 202 |

Os resumos globais e por tenant também registram bytes de resposta; esta atualização conserva a referência ao artifact para a inspeção desses valores sem reproduzir dados de identificação. O runner criou 105 propostas pendentes durante o fluxo da rota de proposta (cinco warmups e 100 amostras), além de uma proposta pendente criada no smoke anterior; nenhuma linha de serviço nova foi criada. Essa contagem é a observação do agente durante a execução, sem pós-contagem SQL independente, porque o banco temporário foi removido ao final.

Essa evidência é local e sintética: não é execução do CI com PostgreSQL 17, nem staging comparável ou produção. Nessa execução não houve perfil contínuo de RSS, CPU ou conexões; continuam faltando volume-alvo/SLO, concorrência e recursos sob carga, filas, Groq, custo, execução CI PostgreSQL 17 e homologação operacional. O scratch, credenciais, chaves, `.env` e banco temporários foram removidos após a coleta. A P0 permanece aberta.

### Repetição de baseline de leituras com monitor de recursos (01/10/2026)

O runner existente foi repetido com cinco warmups e 100 amostras por rota, `P0_INTERVAL_MS=1050`, PostgreSQL 18.4 temporário, PHP 8.5.9, `artisan serve` em `127.0.0.1:8001` e as mesmas fixtures de dois tenants/unidades. Todas as 500 respostas amostradas foram HTTP 200 e `failures=0`.

| Rota | Latência p50 / p95 / p99 (ms) | Query count p50/p95/p99 | SQL time p50 / p95 / p99 (ms) |
| --- | ---: | ---: | ---: |
| `context` | 57.031 / 62.833 / 65.854 | 31 / 31 / 31 | 15.27 / 16.13 / 16.60 |
| `categories` | 58.512 / 63.655 / 68.516 | 32 / 32 / 32 | 15.85 / 16.91 / 17.05 |
| `services` | 59.472 / 64.358 / 66.024 | 32 / 32 / 32 | 16.25 / 17.12 / 17.68 |
| `professionals` | 59.588 / 65.324 / 66.214 | 32 / 32 / 32 | 16.34 / 17.07 / 17.22 |
| `setup_status` | 63.818 / 68.401 / 70.135 | 38 / 38 / 38 | 19.56 / 20.89 / 21.17 |

Artefato agregado sem credenciais/payload: `/private/tmp/caldas-p0-resource-series-20261001/baseline.json` (modo 0600). O monitor gerou apenas duas amostras antes da execução do agente encerrar: Laravel RSS 46.240 KB e PostgreSQL RSS 10.528 KB em ambas; CPU reportada em ambas como 0.0%. Essa série é insuficiente para representar a carga das 500 requisições e não deve ser usada como pico/p95 ou evidência de capacidade. CPU/RSS em série, conexões máximas, concorrência, throughput, custo e erros sob carga seguem pendentes. PostgreSQL e Laravel temporários foram parados; credenciais, chaves, `.env` sintético e dados do banco foram removidos. As portas 55454 e 8001 foram verificadas sem listeners.

### Tentativa diagnóstica reprovada — perfil de recursos (01/10/2026)

Uma tentativa adicional usou o artifact local `/private/tmp/caldas-p0-resource-full-20261001/baseline.json` (modo `0600`), com seis rotas, cinco warmups e 100 amostras por rota. As cinco rotas de leitura concluíram com 100 respostas HTTP 200 cada. `operations_propose` teve apenas 80 respostas HTTP 202, 16 respostas HTTP 500 e 4 `network_error` nos índices 80–99, totalizando 40 falhas; as 20 respostas HTTP 500 também ficaram sem métricas SQL. A causa dos `500` não foi confirmada porque os logs do erro não estão mais disponíveis.

Uma hipótese a investigar é a interação do runner serial com `throttle:20,1` na rota de propostas: como alterna dois usuários a cada ~2,1 s, cada usuário pode passar do limite nominal de 20 chamadas/minuto. Isso não explica por si só os `500` (o middleware normalmente responde `429`) nem o início das falhas no índice 80; portanto, não é causa raiz confirmada. Antes de aceitar um benchmark completo, reproduzir em ambiente descartável registrando apenas índice, alias sintético, status, `Retry-After`, duração e classe/mensagem sanitizada de exceção. Acrescentar cobertura HTTP para o limite, credenciais distintas do mesmo usuário e usuários distintos. Ajustar o ritmo do runner somente após confirmar a interação e sem reduzir o limite da API em produção.

O sampler local para macOS usou uma verificação baseada em `/proc`, que não existe nesse sistema; por isso, as 596 linhas de amostragem ficaram sem métricas (`-1`). O artifact de recursos é inválido e não deve fornecer percentis, substituir o baseline anterior de zero falhas ou ser usado para dimensionamento. O artifact funcional anterior permanece a referência para o que foi medido. A tentativa foi marcada como diagnóstico reprovado; credenciais, chaves, banco, processos e scratch temporários foram removidos, sem alteração de produção. A P0 continua aberta.

### Repetição local controlada por quota e diagnóstico do sampler (01/10/2026)

Após a tentativa reprovada acima, o benchmark completo foi repetido num banco PostgreSQL 18.4 temporário, em `127.0.0.1`, com PHP 8.5.9/`artisan serve`, duas tenants/unidades sintéticas, cinco warmups e 100 amostras por rota. Leituras foram espaçadas em 1050 ms; propostas, em 3600 ms, alternando duas credenciais de usuários distintos. O artefato `/private/tmp/caldas-p0-proposal-baseline-fourth.json` (modo `0600`) registra `failures=[]`, 50 amostras por tenant em cada rota, 100 × HTTP 200 nas leituras, 100 × HTTP 202 em propostas e nenhuma resposta com `Retry-After`.

| Rota | Latência p50 / p95 / p99 (ms) | Queries p50 / p95 / p99 | Tempo SQL p50 / p95 / p99 (ms) | HTTP |
| --- | ---: | ---: | ---: | ---: |
| `context` | 34,095 / 63,055 / 164,618 | 31 / 31 / 31 | 11,97 / 16,00 / 43,55 | 100 × 200 |
| `categories` | 37,809 / 57,949 / 67,545 | 32 / 32 / 32 | 12,89 / 15,89 / 19,21 | 100 × 200 |
| `services` | 37,228 / 57,295 / 63,176 | 32 / 32 / 32 | 13,08 / 15,95 / 18,09 | 100 × 200 |
| `professionals` | 37,022 / 56,672 / 67,914 | 32 / 32 / 32 | 11,58 / 16,39 / 31,11 | 100 × 200 |
| `setup_status` | 37,726 / 58,596 / 68,501 | 38 / 38 / 38 | 14,52 / 18,12 / 23,96 | 100 × 200 |
| `operations_propose` | 56,416 / 80,051 / 85,530 | 69 / 69 / 69 | 24,20 / 28,13 / 28,81 | 100 × 202 |

O corpo médio foi de 147 B em `context`, 543 B em categorias, 1.646 B em serviços, 585 B em profissionais, 474,5 B em `setup_status` e 436 B em propostas. Pós-run, o banco sintético continha 105 propostas pendentes e 160 serviços (sem nova linha de serviço); havia uma conexão ativa no instante da consulta. Esses valores não são pico de conexões nem perfil sob concorrência.

O resumo `/private/tmp/caldas-p0-resource-summary-fourth.json` registra 846 ticks, mas RSS, CPU e conexões estão todos `null`: o sampler local ainda não conseguiu coletar métricas válidas no macOS. Tratar o resultado somente como baseline HTTP/SQL funcional local, não como perfil de recursos. As execuções continuam sem equivalência ao job Ubuntu/PostgreSQL 17, runtime Coolify/staging, load progressivo, telemetria de fila/assistente, custo ou volume/SLO aprovado. O scratch, as credenciais e as chaves temporárias foram removidos; não restaram listeners nas portas 55456 e 8001. A causa das falhas da tentativa anterior permanece desconhecida.

**Reconsulta read-only de runtime (01/10/2026):** a sessão atual conseguiu SSH autenticado ao host e atualizou os snapshots do web, scheduler e PostgreSQL acima, sem ler env ou logs. A consulta direta à API Coolify não foi concluída: não há ferramenta MCP Coolify, a variável `VAULT` não está configurada no processo e nenhuma chave foi extraída de `.env`; a primeira tentativa de DNS dentro do sandbox falhou. Assim, a inspeção do runtime ativo não substitui o estado desejado, réplicas, limites e custos que ainda precisam ser obtidos por uma via autorizada.

## 9. ADRs propostos

### ADR-PERF-01 — Monólito primeiro

**Decisão:** preservar Laravel monolítico e MCP multi-tenant num endpoint.
**Motivo:** menor consumo operacional e menor custo de coordenação; CPU local não executa o modelo de IA.
**Reavaliar:** quando isolamento de recursos, escala de equipe ou limites de carga não puderem ser atendidos com processo/worker separado.

### ADR-PERF-02 — PHP-FPM, sem Octane no MVP

**Decisão:** produção usa FPM + proxy do ambiente, com pool dimensionado por RSS; sem Octane inicialmente.
**Motivo:** concorrência limitada e previsível, sem risco de estado entre requests de workers duradouros.
**Reavaliar:** só com profiling que mostre custo de bootstrap dominante e TE2E/testes de isolamento de estado.

### ADR-PERF-03 — Database queue antes de Redis

**Decisão:** quando a fila for necessária, iniciar com uma fila database e um worker; Redis/Valkey é evolução orientada por métrica.
**Motivo:** PostgreSQL já é uma dependência de produção; evita uma instância gerenciada adicional no começo.
**Reavaliar:** backlog, locks ou carga de polling no banco acima do SLO.

### ADR-PERF-04 — Não habilitar réplicas antes do estado compartilhado

**Decisão:** o piloto permanece single-instance. Para duas ou mais réplicas, quotas/locks/scheduler e mídia precisam de store compartilhado e teste integrado.
**Motivo:** cache local divide o limite por réplica e filesystem local perde estado.

## 10. Riscos, rollback e decisões em aberto

- **Risco de RAM:** não dimensionar FPM a partir de MB comprimidos ou números de blog; coletar RSS sob carga e manter reserva contra OOM.
- **Risco de banco:** fila, cache database e FPM competem pelo mesmo PostgreSQL; observar pool/conexões e migrar backend antes de sobrecarregá-lo.
- **Risco de provedor:** falha/latência/custo Groq; circuit breaker ou estado de falha amigável após implementar job, sem fallback que envie dados a outro provedor automaticamente.
- **Risco de imagem:** imagem comprimida pode decodificar muito maior; upload pré-assinado, validação e job limitado.
- **Risco de disponibilidade:** feature flags mantêm MCP/OAuth fechados até integração e verificação operacional; rollback de aplicação usa tag imutável do runbook, migrations corretivas expand/contract.
- **Decisão operacional pendente:** confirmar com acesso de leitura o tamanho/limite do host atual, alocação de Coolify, custo atual e métricas de uso. Nenhum valor de servidor é assumido como aprovado neste plano.
- **Decisão de produto pendente:** dados de clientes/agenda/vendas permanecem bloqueados conforme o PRD; plano de performance não amplia autorização.

## 11. Prompt-base para a goal futura (não iniciada)

Depois de revisar este plano e aprovar seu escopo, criar uma goal separada com instruções para:

1. executar P0–P5 sequencialmente, tratando P4 e P5 como gates condicionais;
2. respeitar o `AGENTS.md`: raiz orquestra/revisa/testa e subagentes escrevem código/configuração em escopos exclusivos; usar `gpt-5.6-luna` como padrão, no máximo três implementadores em paralelo;
3. preservar todo trabalho preexistente do worktree e verificar as regras/skills antes de entrar em cada área;
4. fazer snapshot das métricas antes e depois de cada alteração, executar Pest/CI e TE2E solicitados pelo projeto, corrigir regressões antes do próximo sprint;
5. interromper apenas a parte dependente quando decisão de privacidade, autorização de escopo ou evidência operacional não existir; continuar tarefas independentes sem ampliar escopo;
6. deixar OAuth desligado, não expor novos domínios e não fazer deploy/publicação sem autorização aplicável;
7. finalizar com revisão completa do diff e evidências, sem declarar metas ou homologações externas que não foram medidas.

**Esta revisão não cria nem inicia uma goal.** A goal deve ser criada somente depois de o usuário revisar e aprovar o plano.

## 12. Referências primárias

- [Laravel 13 — Deployment/optimization](https://laravel.com/framework/docs/deployment): otimização de config, eventos, rotas e views; reinício de serviços de longa duração após deploy.
- [Laravel 13 — Queues](https://laravel.com/framework/docs/13.x/queues): fila síncrona vs. assíncrona, timeouts, reciclagem por tempo/quantidade, monitoramento e memória.
- [Laravel 13 — Rate limiting](https://laravel.com/framework/docs/rate-limiting): escolha do store do limitador e necessidade de estado consistente.
- [PHP — FPM configuration](https://www.php.net/manual/en/install.fpm.configuration.php): modos de gestão de processos e limite de filhos concorrentes.
- [PostgreSQL 17 — Indexes](https://www.postgresql.org/docs/17/indexes-intro.html) e [EXPLAIN](https://www.postgresql.org/docs/17/sql-explain.html): índices conforme consultas e análise do plano real.
- [PostgreSQL 17 — Connections](https://www.postgresql.org/docs/17/runtime-config-connection.html): limites de conexões e custo de aumentá-los.
- [Coolify — Scaling overview](https://coolify.io/docs/core/infrastructure/scaling/overview): início simples e replicação com balanceador externo.
- [Coolify — Self-hosted requirements](https://coolify.io/docs/start-with-self-hosted) e [servers](https://coolify.io/docs/core/infrastructure/servers/overview): recursos do painel e concorrência de workloads no mesmo host.
- [Coolify — Pricing](https://www.coolify.io/pricing): plano do painel não inclui custo do servidor.
- [ADR-007 — storage/uploads](../adr/ADR-007--storage-e-uploads-midia.md): storage S3-compatible e evolução com URL pré-assinada.
- [Runbook Coolify/produção](../operations/production-coolify-white-label.md): topologia implantada documentada, banco, worker, scheduler, storage, health check e rollback.
