# Dossiê canônico de reconstrução

Esta pasta é a **fonte canônica** do levantamento clean-room e das propostas para o novo produto.

## Ordem de leitura

1. `manifest.md` — escopo, autorização, cobertura e lacunas;
2. `claim-ledger.md` — afirmações observadas/inferidas/propostas;
3. `information-architecture.md`, `screens/`, `flows/` e `marketing-strategy-observed.md` — produto e marketing observáveis;
4. `behavior-rules.md` e `network-observations.md` — comportamento e evidência técnica;
5. `domain/` — modelo independente;
6. `api-proposal.md` e `design-system-proposed.md` — contratos e linguagem propostos;
7. `frontend-implementation-plan.md` e `../ROADMAP.md` — ordem de construção e priorização.

## Política de precedência

- Em conflito, evidência e claim ledger prevalecem sobre sínteses.
- `Observed`, `Inferred`, `Proposed` e `Unknown` não podem ser misturados.
- `docs/BELASIS-REVERSE-ENGINEERING.md` é um resumo editorial; não recebe novas evidências diretamente.
- Resumos devem ser revisados em marcos, usando este dossiê como origem.
- Nenhum documento descreve código, API ou banco interno do produto observado.

## Estado atual

Nove batches somente leitura foram concluídos: sete passes autenticados desktop, um passe autenticado responsivo e um passe amplo do marketing público. Em 26/08/2026, a Wave de Navegação 2 foi implementada no Caldas e registrada em `../architecture/wave-navigation-2-sidebar-rail.md`, com evidência funcional adicional em `evidence/EV-010--sidebar-compacta-flyout-desktop.md`. Dispositivos físicos, múltiplos papéis, mutações e rede de fluxos transacionais permanecem lacunas declaradas.
