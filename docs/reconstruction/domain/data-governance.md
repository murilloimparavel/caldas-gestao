# Governança de dados proposta

## Classificação

| Classe              | Exemplos                                           | Tratamento                                                             |
| ------------------- | -------------------------------------------------- | ---------------------------------------------------------------------- |
| Pública             | catálogo publicado                                 | integridade e cache                                                    |
| Interna             | configurações e métricas agregadas                 | RBAC e auditoria                                                       |
| Pessoal             | nome, contato, endereço                            | minimização, finalidade e direitos LGPD                                |
| Sensível            | anamnese, saúde, documentos clínicos               | acesso restrito, criptografia e logging de leitura                     |
| Financeira/fiscal   | pagamentos, conta, nota                            | retenção legal, imutabilidade e segregação                             |
| Segredo             | senha, token, chave, webhook secret                | cofre/hash, nunca logs/eventos/docs                                    |
| Identidade e acesso | email, convite, membership, IP de auditoria        | finalidade de autenticação/segurança, acesso mínimo, retenção definida |
| Evento técnico      | IDs, tenant/unidade, correlação, status de entrega | payload mínimo, sem PII desnecessária, replay controlado               |

## Princípios

- finalidade, base legal, minimização e prazo definidos por campo/processo;
- tenant e unidade aplicados em consulta, índice, cache, job e exportação;
- PII ausente de logs, tracing, eventos e fixtures;
- acesso sensível gera audit event com ator, finalidade e recurso;
- exportações são jobs autorizados, expiráveis e auditados;
- backups cifrados, restore testado e acesso segregado;
- fornecedores têm inventário, DPA, região, suboperadores e plano de saída.

## Ciclo de vida

- coleta com aviso/consentimento quando aplicável;
- uso limitado à finalidade e papel;
- correção com histórico onde necessário;
- anonimização de CRM quando possível;
- retenção financeira/fiscal conforme obrigação legal;
- deleção de anexos/marketing após expiração;
- legal hold impede descarte e fica auditado.

## Direitos do titular

- localizar, exportar, corrigir, anonimizar e excluir conforme base legal;
- separar dados portáveis de registros que precisam ser retidos;
- revogar consentimento interrompe novos envios, não apaga evidência necessária;
- solicitações têm SLA, validação de identidade e trilha completa.

## Segurança operacional

- MFA/step-up para ações sensíveis;
- least privilege, revisão periódica e revogação rápida;
- rotação de chaves/tokens e secrets em cofre;
- detecção de acesso anômalo e exportação em massa;
- ambientes não produtivos usam dados sintéticos;
- incident response inclui avaliação LGPD e comunicação aplicável.

## F2: controles de dados

- `app.users.name`/`email`/`email_normalized`, convites e endereços de unidade são PII; `password` é segredo; nada entra em logs, eventos ou fixtures sem finalidade;
- credenciais, 2FA, tokens e chaves não entram em `audit_events`, `outbox_events`, `inbox_events`, `idempotency_keys.response_ref` ou documentação;
- auditoria registra ator, tenant, unidade, recurso, ação, motivo e correlation ID; diffs são mínimos e redigidos;
- `tenant_id`/`unit_id` devem estar presentes em consultas, jobs, cache e exportações; uma chave de cache sem tenant é incidente;
- idempotency mantém hash da requisição e referência sanitizada por TTL; payload completo não é retenção padrão;
- outbox/inbox armazenam envelope e payload mínimo versionado; dados de CRM, anamnese, fiscal e pagamento são buscados por ID sob Policy;
- anonimização de usuário/membership não remove auditoria, obrigação fiscal ou ledger sob legal hold; o processo deve preservar linhagem sem manter PII além da base legal;
- Supabase managed schemas são tratados como subprocessador/infraestrutura separada; inventário, região, backups, acesso e restore precisam de owner.
- PII production gate bloqueia deploy até secrets, grants, criptografia, retenção, logs/tracing, Redis, backups/restores e acesso operacional serem revisados.

## Lacunas legais/técnicas

Prazos exatos de retenção, encarregado, países/regiões de processamento, bases legais por comunicação, requisitos de prontuário e política de menores exigem validação jurídica e ADRs.

## Cenários de validação

- solicitar exportação de um titular e comprovar que somente memberships/PII autorizadas saem, sem secrets ou payloads de eventos;
- revogar consentimento e impedir novos envios sem apagar evidência necessária;
- anonimizar CRM mantendo reconciliação e auditoria sob retenção;
- verificar que logs, tracing, Redis, fixtures e relatórios não vazam PII por retry, erro ou exportação;
- testar restore de backup e acesso segregado em PostgreSQL `app`.
