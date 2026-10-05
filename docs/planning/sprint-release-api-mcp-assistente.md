# Sprint de prontidão para deploy — API, MCP e assistente

**Data de corte da análise:** 05/10/2026
**Status:** plano de execução; nenhum deploy, push, alteração no Coolify ou ativação de integração externa foi autorizado por este documento.
**Relaciona-se a:** [PRD/ADR da API, MCP e assistente](prd-adr-api-mcp-assistente-ia.md) e [plano de performance e escala econômica](plano-performance-baixo-custo-mcp.md)

## 1. Objetivo da sprint

Levar o trabalho até um ponto em que exista uma decisão de release baseada em evidências, com rollback testado e responsabilidades claras. A sprint fecha as pendências necessárias para publicar o núcleo da aplicação e decide separadamente se a integração MCP para ChatGPT pode ser habilitada.

Há dois produtos de release, com gates diferentes:

1. **Release do núcleo:** aplicação web, credenciais administrativas e contratos internos disponíveis conforme as feature flags aprovadas; MCP externo, OAuth e assistente Groq permanecem desligados até seus gates.
2. **Release MCP/ChatGPT:** transporte externo, OAuth Authorization Code + PKCE, consentimento administrativo, tools e eventual upload de mídia homologados com um cliente real. Esse release não pode ser considerado concluído apenas porque o endpoint HTTP e os testes internos passam.

O objetivo desta sprint é preparar e comprovar os dois caminhos. A decisão de deploy em produção continua sendo uma aprovação posterior e explícita.

## 2. Estado observado em 05/10/2026

As observações abaixo são evidências de leitura, com snapshot operacional às **14:24 UTC** e inspeção de runtime às **14:25 UTC**. Precisam ser reconciliadas com a configuração desejada antes de qualquer mudança. Não foram lidos logs, env, segredos, banco ou dados de negócio/clientes.

| Área | Evidência observada | Consequência para o release |
| --- | --- | --- |
| Web em produção | Container `svurav3cvfovuz8adtv1fuwv-131159015174`, saudável, revisão OCI `4a81bfc4a56eac780b34b71508599efaf527043f`, restart **0**. | Registrar o digest imutável do candidato antes do deploy e confirmar que o container realmente corresponde ao release; a revisão diverge dos demais serviços. |
| Worker em produção | Container `nbhjuwa4qhs5brnwnbqg1ea2-132012325969`, revisão OCI `47b5ba8b9ef0a966503971ca90c55f5d559df3b4`, saudável, exit **0**, `OOMKilled=false`, política `unless-stopped`, comando `php artisan queue:work --sleep=1 --tries=3 --max-time=3600`, restart_count **73**. | O aumento de 72 para 73 entre 13:32 e 14:24 UTC é compatível com reciclagem horária esperada pelo `--max-time=3600`, e não caracteriza por si só crash. Confirmar lag da fila, jobs falhos e drenagem limpa antes/depois do recycle; a revisão ainda diverge. |
| Scheduler em produção | Em execução, sem healthcheck, revisão `96d94f5...`, ativo desde 14/09, comando `php artisan schedule:work`. | Confirmar periodicidade e evidência de execução; web, worker e scheduler devem usar uma release imutável compatível. |
| CI de aplicação | Workflow de testes é disparado em `main` e pull requests. Não havia execução equivalente na branch `production` no momento da consulta. | O artefato de produção não está demonstrado como bloqueado pelos mesmos testes que aprovam o candidato. |
| Build de imagem | Build GHCR `37314709435`, revisão `ebfd62aaa8b4b9a23f2a6d42da0d36fc61c7df28`, concluído em 05/10/2026. O workflow faz build, Wayfinder e push. | O build não substitui CI de Pest/PostgreSQL/Playwright/MCP; falta um vínculo verificável entre checks obrigatórios e publicação. |
| Automação de deploy | O último run observado do workflow de deploy era de 01/10/2026, revisão `dcc7853...`. O workflow usa a tag mutável `production` e atualiza web, sem comprovar atualização coordenada de worker e scheduler. | Corrigir o processo de release ou documentar a operação manual controlada; não assumir que o estado de 05/10 veio do workflow. |
| TE2E do assistente | Em 05/10/2026, os locators foram alinhados aos rótulos pt-BR e o registro de passkey passou a confirmar explicitamente a senha, conforme `password.confirm` das rotas Laravel Passkeys. O job `e2e-assistant` usa `ASSISTANT_ENABLED=true` e `APP_URL`/`PLAYWRIGHT_TEST_BASE_URL=http://localhost:8000`, enquanto `P0_BASE_URL` e Groq fake permanecem em `127.0.0.1`. A execução local integral passou **6/6** no Chromium contra aplicação sintética isolada em SQLite temporário e Groq fake. | As falhas anteriores foram esclarecidas: labels obsoletos em inglês, assistente desativado causando 404 e RP WebAuthn incompatível com host IP. O resultado SQLite local não substitui o job PostgreSQL 17 da CI, que continua pendente. |
| OAuth/MCP externo | Base de transporte, grants e testes negativos existem; OAuth continua desligado. Interoperabilidade real com MCP Inspector/ChatGPT ainda não foi homologada. | Não anunciar conexão ChatGPT pronta; manter flag desligada até a homologação e decisão de autorização. |
| Runtime | Snapshot de 05/10/2026 14:25 UTC confirmou na web rev `4a81bfc` o boot `php artisan migrate --database=migration --force --no-interaction` seguido de `php artisan serve`; scheduler rev `96d94f5` executa `php artisan schedule:work`; worker rev `47b5ba8` executa `queue:work --sleep=1 --tries=3 --max-time=3600`. | Runtime web confirmado e bloqueador: validar em staging migrations one-shot e FPM ou runtime de produção apropriado antes do release. A revisão imutável uniforme entre web/worker/scheduler continua pendente. |

### Limites da evidência

Essas observações são instantâneos. Não provam capacidade sustentada, p95, custo, ausência de erros históricos, configuração de proxy, limites de CPU/RAM, backup, retenção, storage, conexão do banco ou origem exata da revisão em execução. Valores de produção devem ser confirmados por uma fonte operacional autorizada e sanitizada.

## 3. O que ainda falta para o deploy

### Bloqueadores de release

- Definir o candidato de release por commit e digest imutável; não usar apenas `production`.
- Reconciliar web, worker e scheduler: revisão, comando, health, env não sensível, limites de CPU/RAM, fila, scheduler e política de reinício.
- Confirmar lag da fila, jobs falhos e drenagem limpa do worker durante a reciclagem prevista pelo `--max-time=3600`; o contador de restarts, isoladamente, não é bloqueador de release.
- Corrigir o fluxo de runtime web: remover migrations do boot normal, validar migration one-shot e FPM ou runtime de produção equivalente em staging.
- Demonstrar CI obrigatória no candidato: Pest, PostgreSQL compatível, privilégios mínimos/grants, análise estática, build, Playwright e testes do protocolo MCP quando o escopo estiver habilitado.
- Corrigir e repetir a bateria TE2E; atualizar o status documental somente com artefato da execução atual.
- Executar smoke e migração em staging com backup/restauração e caminho de rollback comprovados.
- Confirmar `APP_URL`, proxy confiável, Origin/CORS, cookies, TLS, sessão, storage privado, fila, scheduler e permissões de banco sem expor valores secretos.
- Definir observabilidade mínima: health/readiness, erros 5xx/429, latência, fila, reinícios, conexões PostgreSQL e alertas.
- Testar rollback para a imagem anterior e estratégia para migrations que não podem ser revertidas automaticamente.
- Registrar runbook, owner de plantão, janela, critérios de abortar e evidências anexadas ao release.

### Pendências que não devem ser escondidas dentro do deploy do núcleo

- Homologação com cliente MCP real e ChatGPT elegível.
- OAuth refresh/DCR/CIMD interoperável e aprovação do perfil externo.
- Ativação do chat Groq em produção, caso ainda falte a decisão sobre enviar turno ou histórico, retenção e política da conta.
- Upload de imagem: entidade de destino, URL privada de uso único, quota, validação MIME/dimensão/hash e processamento assíncrono.
- Expansão de dados para clientes, contatos, notas, vendas, caixa ou estoque. O escopo já aprovado permanece limitado ao tenant/unidade do admin e a nomes de categorias, serviços/profissionais, preço, duração e indicadores de setup.
- Escala horizontal, Redis/Valkey, Octane ou novos workers sem evidência de que o gargalo exige isso.

## 4. Execução proposta em paralelo

Cada trilha deve receber uma revisão, arquivos/ambiente exclusivos, critério de aceite e lista de evidências. Os subagentes não devem editar simultaneamente os mesmos workflows, Dockerfiles, rotas, migrations ou testes compartilhados.

| Trilha | Escopo exclusivo | Entrega verificável |
| --- | --- | --- |
| A — Reconciliação operacional | Leitura do Coolify/host autorizada, revisão dos containers, imagens, comandos, health, worker, scheduler, limites e reinícios. Não ler segredos. | Matriz desejado × observado; reciclagem horária prevista pelo `--max-time=3600` reconciliada com exit 0 e `OOMKilled=false`; confirmação pendente de lag da fila, jobs falhos e drenagem limpa; revisão/digest candidato e anterior; riscos de runtime. |
| B — CI e artefato | Auditar workflows de teste, build e deploy; propor o encadeamento por SHA/digest e checks obrigatórios. Não publicar nem alterar workflow sem autorização de implementação. | Pipeline de release desenhado, lacunas listadas, execução do candidato com links/logs redigidos e regra de bloqueio. |
| C — TE2E e segurança | Reproduzir Playwright desktop/mobile, corrigir o desalinhamento de localização no teste/UI no escopo aprovado, verificar admin/colaborador, tenant/unidade, passkey, propostas e MCP negativo. | Matriz TE2E atual; 0 falhas justificadas; artefatos sem PII/segredos; lista de testes não executados. |
| D — Runtime e dados | Validar migrations one-shot, PostgreSQL/grants, backup/restore, sessão/fila/storage, PHP/runtime, health e encerramento gracioso em staging. | Procedimento de release e rollback testado; evidência de que o web não migra no boot normal; orçamento de conexões. |
| E — Documentação e go/no-go | Consolidar runbook, variáveis por nome (sem valores), SLO, alertas, responsáveis, janela e critérios de abortar. | Checklist assinável e decisão separada para núcleo e MCP/ChatGPT. |

Trilhas A–C podem começar em paralelo. D depende da revisão de A para não validar um runtime diferente do implantado. E consolida apenas resultados verificáveis e não transforma pendências em “concluído”.

## 5. Sequência da sprint

### Fase 0 — congelamento e inventário

- Fixar o commit candidato e o commit atualmente servido, sem fazer push ou deploy.
- Exportar somente metadados sanitizados: revisão, digest, status, timestamps, comandos, health e reinícios.
- Registrar as flags efetivas por nome: API, credencial, MCP, OAuth, Groq e upload; valores secretos ficam fora dos artefatos.
- Confirmar banco, storage, sessão, cache, fila e scheduler efetivos.

**Saída:** inventário assinado pelo responsável operacional e lista de discrepâncias.

### Fase 1 — qualidade do candidato

- Rodar os testes exigidos em ambiente limpo e compatível com produção.
- Locators de passkey já estão alinhados ao contrato pt-BR; a jornada de registro confirma a senha antes das rotas Laravel Passkeys. Repetir os 6 cenários no job PostgreSQL 17 da CI, mantendo a execução local SQLite como evidência separada.
- Executar matriz de autorização: owner/admin permitido; colaborador negado; tenant/unidade cruzado negado; grant revogado, API desligada e capability ausente negados.
- Validar MCP HTTP apenas como protocolo enquanto OAuth estiver desligado; separar claramente os testes internos dos testes de produto ChatGPT.
- Redigir traces, screenshots e logs antes de anexar.

**Saída:** relatório TE2E com commit, ambiente, comando, duração, passes, falhas, skips e artefatos sanitizados.

### Fase 2 — staging e recuperação

- Restaurar backup sintético ou validado em staging separado.
- Rodar migrations como tarefa única com credencial administrativa temporária; iniciar web/worker/scheduler depois.
- Confirmar grants de runtime, filas, scheduler, storage privado, cookies/TLS/proxy e `/up`.
- Simular encerramento gracioso, job em andamento, timeout, retry e reinício do worker.
- Fazer rollback para a imagem anterior e validar leitura/login/health; migrations incompatíveis exigem procedimento corretivo documentado, não “rollback” fictício.

**Saída:** evidência de deploy/rollback em staging e limites operacionais medidos.

### Fase 3 — decisão de release

- Revisar bloqueadores com pelo menos um revisor diferente do implementador.
- Aprovar ou rejeitar separadamente o release do núcleo e o release MCP/ChatGPT.
- Se aprovado, registrar a janela e obter autorização explícita para executar o deploy; este documento não fornece essa autorização.

**Saída:** checklist go/no-go preenchido, owner, janela, plano de abortar e comunicação operacional.

## 6. Critérios de entrada

- Escopo de dados e permissões aprovado: admin, tenant/unidade e allowlist documentada.
- Ambiente de staging isolado, com dados sintéticos ou sanitizados, acesso ao PostgreSQL compatível e storage privado.
- Acesso somente leitura às informações operacionais necessárias; nenhum segredo inserido em ticket, log ou artefato.
- Candidato de código identificável; alterações não relacionadas separadas antes da avaliação.
- Owner de aplicação, infraestrutura, segurança e aprovação humana definidos.
- Ferramenta para executar CI/TE2E disponível ou pendência explicitamente registrada; não considerar execução local como substituta da CI PostgreSQL.

## 7. Critérios de saída e definição de pronto

### Pronto para release do núcleo

- Candidato imutável passou checks obrigatórios e TE2E atual, sem falhas conhecidas sem decisão.
- Web, worker e scheduler usam a mesma release compatível ou a diferença está formalmente justificada.
- Reinícios do worker têm causa conhecida e não há erro recorrente sem mitigação.
- Backup/restore, migration one-shot, health, graceful shutdown e rollback foram exercitados em staging.
- Segredos permanecem em secret store; logs, traces e props não exibem tokens, credenciais ou PII.
- Admin/tenant/unidade, revogação, quotas e feature flags foram testados; MCP/OAuth/Groq externo continuam desligados se seus gates não estiverem aprovados.
- Runbook e suporte têm comandos, responsáveis, métricas, janela e critérios de abortar.

### Pronto para release MCP/ChatGPT

Todos os critérios do núcleo, mais:

- OAuth Authorization Code + PKCE S256, redirect/resource/audience/state e revogação verificados com cliente compatível.
- Consentimento exige admin, contexto tenant/unidade, capabilities e step-up conforme ADR; colaborador é negado em descoberta, consentimento, `tools/list` e `tools/call`.
- MCP Inspector e ChatGPT elegível completam leitura e proposta; escrita segue proposta + confirmação humana, sem autoaprovação.
- Compatibilidade de headers/content type/Origin, rate limit por identidade/IP e proteção contra replay/CSRF/SSRF confirmadas.
- Política de retenção, redaction, custo e payload enviado ao Groq aprovada; chat externo não envia campos proibidos.
- Upload, se incluído, usa entidade aprovada, URL privada curta, quota e processamento seguro; caso contrário permanece desligado.

## 8. Go/no-go

| Decisão | Pode avançar quando | Deve parar quando |
| --- | --- | --- |
| Núcleo | Checks, TE2E, staging, recuperação, observabilidade e runbook aprovados; candidato imutável reconciliado. | Exit inesperado, job falho, trabalho perdido ou lag da fila; revisão/digest divergente; migration/backup/rollback não provados; 5xx/429/segredo/PII sem tratamento; teste essencial falha. O contador de reciclagens esperadas não bloqueia sozinho. |
| MCP/ChatGPT | Núcleo aprovado, OAuth/interop real homologada, autorização externa registrada, allowlist e consentimento revisados. | OAuth desligado, interop não testada, colaborador ou tenant cruzado acessa algo, escrita sem confirmação, upload sem destino ou política Groq pendente. |

Um “go” para o núcleo não é um “go” para MCP/ChatGPT. Um teste interno do protocolo não é prova de conexão funcional no ChatGPT.

## 9. Rollback e resposta a falha

1. Interromper a promoção e desativar imediatamente as flags externas.
2. Fixar o digest anterior conhecido e restaurar web, worker e scheduler de forma coordenada.
3. Não apagar dados nem desfazer migration automaticamente. Aplicar procedimento corretivo versionado quando necessário.
4. Confirmar `/up`, login administrativo, autorização, fila, scheduler e leitura básica antes de reabrir tráfego.
5. Verificar duplicidade de jobs/propostas, eventos, logs de segredo/PII, conexões e reinícios.
6. Registrar hora, revisão, sintoma, impacto, comando executado e evidências; abrir follow-up de causa raiz.

O rollback deve ser testado em staging com o mesmo formato de imagem e comandos previstos para produção. Restaurar somente o web e deixar worker/scheduler em outra revisão não é rollback completo.

## 10. Riscos e dependências abertas

| Item | Estado em 05/10/2026 | Ação de fechamento |
| --- | --- | --- |
| Origem do deploy de `ebfd...` | Não confirmada; não há run correspondente de deploy observado. | Reconciliar Coolify/GitHub por fonte autorizada e registrar o evento. |
| Configuração real do Coolify | Não confirmada; não imprimir env/secrets. | Obter snapshot sanitizado com limites, comandos, réplicas, storage, fila e custo. |
| Worker com 73 restarts | O worker está saudável, com exit 0, `OOMKilled=false`, política `unless-stopped` e `--max-time=3600`; a evolução de 72 para 73 em cerca de uma hora é compatível com recycle planejado. | Confirmar lag da fila, jobs falhos e drenagem limpa; não tratar o contador, sozinho, como bloqueador. |
| Scheduler antigo | Revisão/idade observadas, compatibilidade não confirmada. | Confirmar imagem, comando, periodicidade e sincronização com candidato. |
| CI bloqueando imagem | Não comprovado. | Ligar publicação/deploy a checks obrigatórios por SHA; executar candidato. |
| TE2E 6/6 | Confirmado em 05/10/2026 no Chromium, com ambiente SQLite sintético isolado, Groq fake, locators pt-BR, confirmação de senha e RP `localhost`. | Executar o job PostgreSQL 17 da CI; manter o resultado local separado dessa evidência. |
| MCP externo | OAuth desligado e interop pendente. | Homologar somente após autorização; manter flag desligada. |
| PHP/runtime | Confirmado em 05/10/2026 14:25 UTC: web migra no boot e serve com `artisan serve`; worker e scheduler usam comandos próprios. | Validar migration one-shot e FPM ou runtime de produção equivalente em staging; uniformizar a revisão imutável dos três serviços antes do release. |
| Banco/storage/backup | Não provados nesta leitura. | Exercitar grants, restore e armazenamento privado sem dados reais. |

## 11. Checklist executável

### Antes da implementação do release

- [ ] Escopo e feature flags confirmados.
- [ ] Candidato, base e árvore de mudanças separados.
- [ ] Revisões/digests web, worker e scheduler coletados.
- [x] Reciclagem esperada do worker verificada: `--max-time=3600`, exit 0 e `OOMKilled=false`.
- [ ] Lag da fila, jobs falhos e drenagem limpa durante a reciclagem confirmados.
- [ ] Workflow de CI/build/deploy auditado.
- [x] Locator de passkey alinhado ao pt-BR, confirmação de senha registrada e TE2E local repetido: Chromium **6/6**, SQLite sintético isolado e Groq fake.
- [ ] TE2E repetido no job PostgreSQL 17 da CI.

### Antes do staging

- [ ] PostgreSQL, grants e versão compatível confirmados.
- [ ] Backup/restore verificado.
- [ ] Secret store, APP_URL, proxy, Origin/CORS, cookies e TLS confirmados.
- [ ] Queue/cache/session/storage/scheduler definidos.
- [ ] Migration one-shot e health/readiness testados.
- [ ] Limites de CPU/RAM, conexões e reinício gracioso medidos.

### Antes do go do núcleo

- [ ] CI obrigatória verde no candidato.
- [ ] TE2E sem falha não decidida e artefatos redigidos.
- [ ] Smoke admin, colaborador, tenant/unidade e revogação verde.
- [ ] Rollback do conjunto web/worker/scheduler verde.
- [ ] Alertas, runbook e responsáveis confirmados.
- [ ] Autorização explícita de deploy obtida separadamente.

### Antes do go de MCP/ChatGPT

- [ ] OAuth PKCE e consentimento real homologados.
- [ ] MCP Inspector e ChatGPT elegível testados.
- [ ] Tools, scopes, tenant/unidade, quotas e auditoria revisados.
- [ ] Escritas continuam propostas e exigem confirmação humana.
- [ ] Groq e retenção/payload aprovados, se chat for ativado.
- [ ] Upload aprovado e testado, ou flag explicitamente desligada.

## 12. Resultado esperado desta sprint

Ao final, haverá um candidato identificado, uma matriz do estado de produção, uma bateria TE2E atualizada, evidências de staging/rollback, lacunas de automação documentadas e duas decisões de release independentes. Se algum gate permanecer sem evidência, o resultado correto é “não pronto” com a ação de fechamento correspondente; não se deve marcar o deploy como completo por inferência.
