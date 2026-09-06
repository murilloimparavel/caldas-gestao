# ADR-009 — Integração outbound com Google Calendar

- **Status:** aceito para MVP
- **Data:** 05/09/2026
- **Decisão:** Sincronizar appointments do Caldas Gestão para o Google Calendar por OAuth opcional e jobs idempotentes, sempre delimitados por tenant e unidade.

## Contexto

O MVP precisa publicar compromissos operacionais do Caldas Gestão no Google Calendar de uma unidade, sem tornar o Google uma fonte de verdade para agenda, autorização ou tenancy. A integração deve tolerar credenciais ausentes, retries e falhas temporárias sem duplicar eventos nem atravessar tenants.

## Decisões

### Escopo outbound

O Caldas Gestão permanece a autoridade do appointment. Eventos `appointment.created`, `appointment.updated` e `appointment.cancelled` disparam a sincronização outbound após o commit da transação. O MVP cria, atualiza e remove eventos no calendário configurado; não importa eventos externos nem resolve conflitos bidirecionais.

### OAuth opcional

Cada unidade pode conectar uma conta Google por OAuth Authorization Code com PKCE. O state é aleatório, armazenado somente como hash, vinculado a usuário/tenant/unidade, expira e é consumido sob lock. Access e refresh tokens são armazenados com cast criptografado e nunca fazem parte das respostas de status.

Se client ID, secret ou redirect URI não estiverem configurados, a integração fica `not_configured`; a agenda local continua funcionando e o job termina sem efeito quando não existe conexão ativa.

### Tenancy e unidade

`GoogleCalendarConnection`, `GoogleCalendarOAuthState` e `GoogleCalendarEvent` carregam `tenant_id` e `unit_id`. As migrations usam FKs compostas para impedir associação cruzada entre unidade, appointment e conexão. Controllers, OAuth e jobs revalidam o contexto; nenhum identificador recebido do frontend define sozinho o escopo.

### Outbox, job e idempotência

O dispatch ocorre com `afterCommit`, evitando chamadas externas dentro da transação operacional. `SyncGoogleCalendarAppointment` localiza a conexão da mesma unidade, mantém um registro único por conexão/appointment e usa o `google_event_id` persistido para escolher PATCH ou DELETE. Um PATCH 404 volta para POST e um DELETE 404 é tratado como já removido.

O job usa timeout explícito, refresh token quando necessário, retries de fila com backoff e payload determinístico com hash. `ReplayGoogleCalendarOutbox` permite reprocessamento limitado de eventos de appointment. A sincronização pode ser repetida sem criar um segundo evento lógico no banco.

### Privacidade e segurança

O payload outbound contém apenas o necessário para representar o compromisso: título, observação e início/fim com timezone. Tokens são secretos e criptografados; erros persistidos devem ser sanitizados e não podem conter access/refresh token. A conexão, o calendário e o e-mail da conta são visíveis somente a usuários autorizados no tenant/unidade.

O desligamento remove as credenciais locais e impede novos envios. Revogação remota do grant Google e sincronização inbound ficam fora do MVP e devem ser decididas antes de serem implementadas.

## Limitações conhecidas

- O MVP não importa alterações feitas no Google e não oferece resolução bidirecional de conflitos.
- A seleção é limitada ao calendário configurado (inicialmente o calendário primário).
- Se o refresh token for revogado, o job entra em retry/dead-letter e a conexão precisa ser reconectada.
- Credenciais ausentes não são erro de agenda; apenas desabilitam a integração.
- A worktree `google-calendar` possui uma limitação de bootstrap do Pest: os testes falham antes da execução por resolução de namespace/configuração (`Target class [config] does not exist`). PHP lint e Pint passam; a suíte deve ser reexecutada no ambiente/branch com o bootstrap normal.

## Alternativas consideradas

### Google Calendar como fonte de verdade

Rejeitado. A agenda local contém autorização, disponibilidade, status e invariantes multi-tenant que não podem depender de um provedor externo.

### Sincronização síncrona dentro da requisição

Rejeitado. A chamada externa aumentaria latência e faria falhas do Google abortarem mutações locais; o outbox/job permite retry e observabilidade.

### Credenciais globais da aplicação

Rejeitado. Uma credencial global não expressa isolamento por unidade e aumenta o impacto de vazamento ou revogação.

## Consequências

### Positivas

- A agenda local continua disponível sem configuração Google.
- Retries e replay não exigem intervenção manual para cada appointment.
- Constraints de banco e escopo explícito reduzem risco de vazamento entre tenants.
- O desenho permite adicionar outros provedores sem transformar o Google em dependência do domínio.

### Custos e riscos

- Workers, outbox e dead-letter precisam de monitoramento.
- Tokens revogados exigem reconexão pelo usuário.
- A sincronização outbound pode ficar temporariamente atrasada em relação à agenda local.
- A integração ainda requer testes de contrato e execução em ambiente com Pest corretamente inicializado.

## Critérios para revisar este ADR

Revisar quando houver necessidade de importar eventos, sincronizar múltiplos calendários, suportar outros provedores, revogar grants remotamente, tratar conflitos bidirecionais ou disponibilizar a integração para mais de uma conta por unidade.

## Referências

- [ADR-002 — Fundação de dados e tenancy](ADR-002--fundacao-de-dados-e-tenancy.md)
- [ADR-006 — Retenção legal, anonimização e legal hold](ADR-006--lgpd-retencao-e-anonimizacao.md)
