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
