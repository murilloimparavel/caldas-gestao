# Máquinas de estado propostas

Estas máquinas são propostas independentes; não representam implementação interna do alvo.

## Acesso e entitlement (fundação F2)

```text
Membership: invited → active → suspended → active
                         └──────→ revoked → invited (reinvite auditado)

Entitlement: trial → active → grace → suspended → expired
                         └──────────────→ revoked (terminal)
```

Guards propostas: ativação exige identidade verificada e tenant válido e só ocorre por `ActivateMembership` Action transacional, que bloqueia membership + unidades do tenant e exige membership unit ativa ou papel tenant-wide ativo; update direto de status é proibido. `suspended`/`revoked` impede novos requests e jobs; somente comando de reinvite auditado pode mover `revoked` para `invited`; `expired` pode exibir benefício/paywall, mas não concede capacidade. A transição de acesso é auditada e invalida caches de autorização. O papel pode ser removido sem apagar o histórico de membership.

## Entrega de evento e idempotência

```text
Outbox: pending → available → publishing → published
                             ↘ retryable → available
                             ↘ dead (replay explícito)
             publishing --lease expirado/reaper--> retryable

Inbox: received → processing → processed
                    ↘ retryable → processing
                    ↘ failed → retryable (comando explícito)
                    ↘ dead (forward-fix/replay)
             processing --lease expirado/reaper--> retryable
```

Outbox e inbox usam claim atômico com `FOR UPDATE SKIP LOCKED`, `locked_by`, `locked_at` e `lease_until`; reaper recupera leases vencidos sem duplicar o evento. Uma chave idempotente segue `started → succeeded|failed` e só pode ser reutilizada após expiração segundo política explícita; request hash diferente é conflito. Esses estados são infraestrutura proposta, não estados observados no alvo.

## Obrigação financeira

```text
draft → scheduled → available → partially_settled → settled
                   ↘ overdue
available/overdue → blocked → available
qualquer não final → cancelled
settled → adjusted/reversed
```

## Caixa

```text
closed → opening → open → counting → closed
                            ↘ disputed → closed
closed → reopened (papel elevado + motivo)
```

## Comissão

```text
projected → accrued → approved → payable → paid
                 ↘ disputed
paid → adjusted
```

## Documento fiscal

```text
draft → queued → processing → authorized
                    ↘ rejected → corrected → queued
authorized → cancel_requested → cancelled
```

Estados operacionais diferentes jamais devem ser representados por um único booleano `paid`, `closed` ou `issued`.

## Validação e ADRs pendentes

- testar transições concorrentes de revogação e processamento de inbox no PostgreSQL;
- testar replay de outbox sem duplicar venda, pagamento, estoque ou mensagem;
- validar status de entitlement com autorização e UX de recurso não contratado;
- avaliar vigência com `as_of` nas bordas `starts_at`/`ends_at`, sem depender de índice parcial com `now()`;
- ADR pendente para RLS, retenção de estados terminais e particionamento de eventos.
