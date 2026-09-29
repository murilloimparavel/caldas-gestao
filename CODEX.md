# Política de trabalho dos agentes

- O agente raiz é o orquestrador: define a fatia, coordena os agentes, revisa o resultado final, executa testes e cuida do GitHub, commits e push. Ele não escreve código nem configuração da implementação.
- Um subagente implementador escreve uma fatia delimitada da tarefa.
- Um segundo subagente, independente, revisa a implementação em somente leitura e registra os achados.
- Após a revisão, um agente corretor aplica os achados aprovados. O agente raiz valida novamente e decide se a fatia está pronta.
- Evite edições concorrentes sobrepostas; cada agente deve ter um escopo de arquivos claramente delimitado.

## Política de modelos

- Use `gpt-5.6-luna` por padrão.
- Escale para `gpt-5.6-terra` quando houver erros repetidos, falta de progresso ou complexidade arquitetural; se necessário, escale depois para `gpt-5.5`.
- Nunca use `gpt-5.6-sol` sem autorização explícita do usuário.

## Política de revisão eficiente

- Faça revisão de desenho antes da implementação somente em mudanças transversais, como schema, estados, concorrência ou permissões.
- Faça uma revisão final independente, focada no diff e na checklist de aceite da fatia.
- Correções P0/P1 retornam ao autor; a validação seguinte deve ser pontual e limitada às correções, sem repetir uma auditoria integral.
- Tarefas mecânicas ou isoladas dispensam revisor dedicado: use validação automática e inspeção do agente raiz.
- Quando o frontend depender do backend, finalize primeiro os contratos e o backend, gere o Wayfinder e só então implemente o frontend.

## GitHub, revisão e deploy

- Preserve o estado local: antes de mudar de branch ou preparar um commit, confira `git status --short --branch` e não descarte alterações que não pertencem à tarefa.
- Use uma branch de trabalho com o padrão `codex/<slug>` e commits no padrão Conventional Commits, por exemplo `fix: restore inventory summary`.
- Fluxo normal: confira `git status --short --branch`; crie `codex/<slug>`; revise o diff staged com `git diff --cached` e `git diff --cached --check`; faça um commit Conventional Commit; publique com `git push -u origin codex/<slug>`; abra o PR com `gh pr create --base main`; e revise-o com `gh pr view`, `gh pr diff` e `gh pr checks`.
- Antes de abrir ou revisar um PR, confira `gh pr view <numero> --repo murilloimparavel/caldas-gestao`, `gh pr diff <numero> --repo murilloimparavel/caldas-gestao` e `gh pr checks <numero> --repo murilloimparavel/caldas-gestao`.
- Para release ou hotfix, confirme explicitamente a branch base do PR antes de abrir ou mesclar; não presuma `main` ou `production`. Merge, push para `production` e deploy são ações externas e exigem autorização explícita do usuário. Não presuma o método de merge: confirme o método configurado ou use o método pedido pelo usuário.
- Após uma publicação, confirme o SHA da branch, o SHA do PR mesclado e o run correspondente em `gh run list`/`gh run view`. Registre também se o build e o deploy terminaram com sucesso.

O build de produção em `.github/workflows/build-ghcr.yml` roda por `push` na branch `production`. O deploy em `.github/workflows/deploy-coolify.yml` roda somente quando o build de produção termina com sucesso e informa `head_branch == 'production'`. Um push em `main` não publica nem implanta produção.

Para rever uma execução, use `gh run view <run-id> --repo murilloimparavel/caldas-gestao` e confira evento, branch, SHA e jobs antes de reexecutar. Reexecuções podem publicar uma imagem ou alterar produção; só faça isso com autorização explícita e depois de confirmar que a execução pertence à branch `production`. O `workflow_dispatch` foi removido da versão desses workflows no default branch; por isso o GitHub não oferece dispatch manual, inclusive para versões antigas escolhidas no seletor de branch. A recuperação normal é corrigir e publicar um commit em `production`, seguindo o fluxo de PR autorizado.
