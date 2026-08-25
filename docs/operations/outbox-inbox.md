# Operação de outbox e inbox

## Contrato

As Actions gravam o fato, `audit_events` e `outbox_events` na mesma transação. O dispatch ocorre somente depois do commit. O comando `outbox:pump` primeiro recupera leases expirados, reclama um lote com token exclusivo e agenda `DispatchOutboxEvent` para o consumidor local configurado em `OUTBOX_CONSUMER` (exposto em `config/queue.php` como `queue.outbox_consumer`).

O job só publica quando o evento continua em `publishing`, pertence ao token do lote e possui lease válido. A entrega local é deduplicada por `(consumer, event_id)` em `inbox_events`; o inbox não é um placeholder de integração externa. Toda transição terminal é um `UPDATE` condicional e falha se o worker perdeu o lease.

## Operação periódica

O scheduler executa `outbox:pump` e `inbox:reap` a cada minuto, com `withoutOverlapping` e `onOneServer`. Em produção, o worker da fila `outbox` deve estar ativo e o cache compartilhado (Redis recomendado) deve estar configurado para que os locks do scheduler sejam distribuídos entre instâncias. Um pump manual pode limitar o lote:

```sh
php artisan outbox:pump --limit=50 --lease=60
php artisan inbox:reap --limit=100
```

`attempts` aplica backoff exponencial limitado. Após o máximo configurado, o evento vai para `dead`; leases expirados são recuperados com `FOR UPDATE SKIP LOCKED` no PostgreSQL.

## PostgreSQL e privilégio operacional

O papel de migration/provisionamento é o dono das tabelas e instala os guards. O papel runtime recebe somente DML necessário; não recebe `TRIGGER` ou `TRUNCATE`. `audit_events` é append-only por trigger `BEFORE UPDATE OR DELETE`, e qualquer atualização ou deleção de uma role `is_system=true` é rejeitada, inclusive alterações de timestamps ou `lock_version`. Manutenção exige migration forward-fix pelo papel owner, nunca bypass no runtime.

### Modelo de confiança do owner

A role de migration é o owner operacional confiável e auditado do schema: ela é o único bypass para manutenção controlada, pois o owner pode alterar/remover guards ou usar operações que não disparam trigger de linha, como `TRUNCATE`. Portanto, append-only é garantido contra a role runtime — que não possui `UPDATE`, `DELETE`, `TRUNCATE` ou `TRIGGER` sobre `audit_events` — e não contra um owner hostil ou credenciais administrativas comprometidas. Qualquer operação pela role owner exige change control, revisão, trilha de auditoria, backup/rollback e um forward-fix documentado; nunca é executada por workers, requests ou rotina de runtime.

SQLite valida o contrato de aplicação e as transições, mas não simula grants, triggers, `jsonb`, índices parciais ou concorrência PostgreSQL. Esses gates devem rodar no CI/staging PostgreSQL antes do deploy.

O CI usa PostgreSQL 17 como gate de referência. A validação local também foi executada em PostgreSQL 18; os recursos usados nesta wave (`NULLS NOT DISTINCT`, `jsonb`, triggers, índices parciais e `FOR UPDATE SKIP LOCKED`) são compatíveis entre essas versões, mas a aprovação de merge continua dependente do CI em 17.

Falhas do consumidor permanecem no inbox com lease/token e estado terminal. Não existe retry público sem autorização: a reabertura de uma entrada `failed` exige uma operação operacional auditada e uma capability própria, a ser criada em uma Action futura.
