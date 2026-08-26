# Sprint — Arquitetura, Resiliência, LGPD e Sanitização de Logs

## 🎯 Objetivo
Atender aos requisitos de **Estratégia e Segurança de Longo Prazo (Sprint 3)** do levantamento de dívidas técnicas:
1. Formalizar as especificações de arquivação, retenção legal e chaveamento UUIDv7 (**ADR-005** e **ADR-006**).
2. Garantir a imutabilidade dos logs de auditoria (*append-only*).
3. Implementar a sanitização automática de dados PII (dados pessoais sensíveis) nos registros de log da aplicação.

---

## 📦 Entregas Realizadas

### 1. Novas Decisões Arquiteturais (ADRs)
- **[`docs/adr/ADR-005--uuidv7-padronizacao.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/adr/ADR-005--uuidv7-padronizacao.md)**:
  - **Status:** Aceito.
  - **Decisão:** Padronização do uso de UUIDv7 (Time-Ordered UUIDs) para chaves primárias relacionais em tabelas operacionais, auditorias e logs de alta frequência. Melhora de performance no índice B-Tree do PostgreSQL sem perder a não-previsibilidade.
- **[`docs/adr/ADR-006--lgpd-retencao-e-anonimizacao.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/adr/ADR-006--lgpd-retencao-e-anonimizacao.md)**:
  - **Status:** Aceito.
  - **Decisão:** Diretrizes de retenção legal (5 anos conforme legislação fiscal), rotinas de expurgo/anonimização (soft delete + hashing de PII) e implementação de flag de *Legal Hold* para suspensão de expurgo sob ordens judiciais.

### 2. Testes de Imutabilidade de Auditoria ([`tests/Feature/AuditImmutabilityTest.php`](file:///Users/murilloalves/Projects/caldas-gestao/tests/Feature/AuditImmutabilityTest.php))
- **Garantia Append-Only:** Bloqueio de mutação ou exclusão direta nos eventos de auditoria com exceções `LogicException('Audit events are append-only.')`.
- **Validação PII:** Testes verificando o mascaramento no context/extra dos logs.

### 3. Mascaramento Automático de PII em Logs ([`app/Logging/MaskSensitiveDataProcessor.php`](file:///Users/murilloalves/Projects/caldas-gestao/app/Logging/MaskSensitiveDataProcessor.php))
- **Filtro Monolog:** Registrado em `config/logging.php` para os canais `single` e `daily`.
- **Campos Sanitizados:** `cpf`, `phone`, `password`, `credit_card`, `card_number`, `cvv`, `secret`, `token`.

---

## 🧪 Validação Geral
- **Pest:** 302 testes executados (**277 aprovados**, 25 skipped, **0 falhas**).
- **PHPStan:** **0 erros** (`errors: 0`).
- **ESLint & TypeScript:** 0 erros/warnings.
- **Graphify:** Grafo reconstruído com sucesso.
