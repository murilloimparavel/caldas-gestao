# Governança de dados proposta

## Classificação

| Classe | Exemplos | Tratamento |
|---|---|---|
| Pública | catálogo publicado | integridade e cache |
| Interna | configurações e métricas agregadas | RBAC e auditoria |
| Pessoal | nome, contato, endereço | minimização, finalidade e direitos LGPD |
| Sensível | anamnese, saúde, documentos clínicos | acesso restrito, criptografia e logging de leitura |
| Financeira/fiscal | pagamentos, conta, nota | retenção legal, imutabilidade e segregação |
| Segredo | senha, token, chave, webhook secret | cofre/hash, nunca logs/eventos/docs |

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

## Lacunas legais/técnicas

Prazos exatos de retenção, encarregado, países/regiões de processamento, bases legais por comunicação, requisitos de prontuário e política de menores exigem validação jurídica e ADRs.
