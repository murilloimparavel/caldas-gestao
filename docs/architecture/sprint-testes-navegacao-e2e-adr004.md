# Sprint — Testes de Permissão de Navegação, Playwright E2E e ADR-004

## 🎯 Objetivo
Atender aos requisitos de **Projetos Chave (Sprint 2)** do levantamento de dívidas técnicas:
1. Validar a renderização e o bloqueio de permissões de navegação (`rail`, `flyout`, rotas) conforme papéis de `MembershipRole`.
2. Implementar a infraestrutura e a suíte inicial de testes E2E com Playwright.
3. Formalizar a decisão de arquitetura de banco de dados para RLS e Supabase Session Pooler (**ADR-004**).

---

## 📦 Entregas Realizadas

### 1. Testes de Permissão de Navegação (`tests/Feature/NavigationPermissionTest.php`)
- **Padrão:** Pest PHP.
- **Cobertura:**
  - Usuários `owner` e `manager`: Acesso completo e props de navegação desbloqueadas.
  - Usuários `professional` e `receptionist`: Permissões restritas, menu filtrado e rotas financeiras protegidas com `HTTP 403 Forbidden`.
  - Propriedades compartilhadas do Inertia (`auth.user`, `auth.permissions`, `workspace.*`).
- **Resultado:** 5 testes executados e aprovados (90 asserções).

### 2. Infraestrutura de Testes E2E com Playwright
- **Configuração:** `playwright.config.ts` apontando para a suíte `./tests/e2e` e suporte a `PLAYWRIGHT_TEST_BASE_URL`.
- **Suíte E2E Público:** `tests/e2e/public-booking.spec.ts` cobrindo o fluxo de agendamento público (`/agendar/{unit_slug}`), listagem de serviços, seleção de profissional e horários.
- **Script NPM:** Adicionado `"test:e2e": "npx playwright test"` em `package.json`.

### 3. ADR-004 — RLS e contexto de tenant com Supabase Session Pooler
- **Local:** `docs/adr/ADR-004--supabase-rls-e-pooler.md`.
- **Status:** Aceito.
- **Decisão:** Laravel como autoridade primária de autorização/tenancy, com adoção gradual de Row Level Security (RLS) como defesa em profundidade executando `SET LOCAL app.current_tenant_id = 'tenant-uuid'` dentro de transações do Postgres. Suporte a Session Pooler com princípio do menor privilégio (`caldas_runtime`).

---

## 🧪 Validação Geral
- **Pest:** 299 testes executados (**274 aprovados**, 25 skipped, **0 falhas**).
- **PHPStan:** 0 erros (`errors: 0`).
- **ESLint & TypeScript:** 0 erros/warnings.
- **Graphify:** Grafo atualizado.
