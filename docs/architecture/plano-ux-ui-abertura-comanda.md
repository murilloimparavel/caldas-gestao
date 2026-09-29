# Plano de melhoria UX/UI — Abertura de comanda

**Status:** Proposto
**Escopo:** fluxo iniciado pelo botão **Nova comanda** na página `/sales`; não altera as regras de negócio de checkout.
**Evidências:** captura enviada pelo usuário em 29/09/2026; implementação atual em `resources/js/pages/sales/index.tsx`; criação rápida em `resources/js/components/operational/quick-create-dialogs.tsx`; domínio de abertura em `docs/PRD--comandas-categorias-e-checkout.md`.

## Objetivo

Permitir que a equipe abra uma comanda rapidamente, encontre o cliente com poucos caracteres e entenda quais dados são necessários para aquela categoria. O fluxo deve servir tanto operações identificadas (salão/barbearia) quanto consumo avulso, mesas e referências.

## Diagnóstico baseado em evidência

### Observado

- A ação **Nova comanda** abre um `Dialog` com categoria, cliente opcional, referência/mesa e observações.
- A categoria já exibe tipo de itens e regra de unicidade como texto auxiliar.
- Cliente é um `<select>` nativo preenchido com toda a lista recebida pela página. A captura mostra o menu expandido cobrindo boa parte do modal e da tela.
- Há ação **+ Novo Cliente**. O cadastro rápido já escolhe o registro criado ao retornar.
- O modal tem largura máxima `sm:max-w-xl`; a lista nativa permanece dependente da UI do navegador e não oferece busca evidente.
- Os campos de referência e observações aparecem sempre, independentemente da categoria selecionada.
- A criação envia a categoria, cliente, referência e observações, com chave de idempotência.

### Avaliação UX/UI

1. **Busca de cliente é o bloqueio principal.** Com uma lista extensa, rolar opções em ordem alfabética é lento e propenso a selecionar a pessoa errada.
2. **A hierarquia não acompanha a tarefa.** Categoria, identificação do cliente, referência e nota têm peso visual semelhante, embora cliente/categoria determinem o contexto da comanda.
3. **Configuração compete com velocidade.** A regra de categoria está exposta como informação auxiliar, mas não orienta claramente quais dados são necessários ou relevantes.
4. **Modal alto e sobreposição ruim.** O menu do select escapa visualmente do contêiner e esconde campos/ações; na captura, o usuário não consegue inspecionar o formulário inteiro enquanto escolhe.
5. **O fluxo não deixa explícita a próxima etapa.** O botão abre a comanda, mas a interface deve deixar claro que itens e cobrança serão adicionados depois, no detalhe da comanda.

## Direção de experiência

Manter o padrão visual operacional já existente no produto, com hierarquia mais nítida, densidade controlada e foco na ação. Evitar introduzir uma nova linguagem visual neste fluxo.

O modal passa a ser um formulário curto e progressivo:

1. **Categoria** — primeira escolha; opção padrão pode ser preselecionada somente se existir uma escolha única e segura para a unidade. Caso contrário, exigir seleção explícita.
2. **Cliente ou atendimento avulso** — busca imediata, com opção avulsa claramente disponível e cadastro rápido ao alcance.
3. **Referência** — mostrar e priorizar quando fizer sentido para a categoria (ex.: mesa/pedido); em outras categorias, deixar opcional e recolhida/menos proeminente, sem descartar valor já digitado ao trocar categoria.
4. **Observações** — campo secundário opcional, recolhido por padrão sob “Adicionar observação”.
5. **Ação fixa** — cancelar e **Abrir comanda**, com estado de carregamento e prevenção de envio repetido.

### Busca e seleção de cliente

- Substituir o select nativo por um combobox acessível e pesquisável.
- Pesquisa por nome e telefone, com normalização de espaços, pontuação e acentos para facilitar correspondências em dados brasileiros.
- Mostrar resultados limitados e legíveis, com nome em destaque e telefone como dado de confirmação; realçar a correspondência sem mudar o conteúdo cadastrado.
- Incluir estados: vazio inicial com instrução curta, digitando, resultados, nenhum resultado, carregando (se busca remota), erro e cliente selecionado.
- Disponibilizar “Cliente avulso / não identificado” como ação explícita, sempre fácil de encontrar; não confundir com resultado vazio.
- Manter “+ Novo cliente” próximo ao campo. Ao concluir o cadastro inline, fechar o cadastro, selecionar automaticamente a pessoa, mostrar confirmação visual e devolver o foco ao fluxo da comanda.
- Se a busca continuar local, limitar a quantidade renderizada e medir o tamanho do payload. Se volume/latência justificar busca remota, adotar debounce e endpoint autorizado por unidade/tenant; decidir isso após medir a lista real, sem presumir infraestrutura.

## Proposta de estrutura do modal

```text
Abrir comanda                                      [fechar]
Escolha a categoria e identifique o atendimento.

Categoria *
[ Loja / Barbearia                              v ]
Misto · Livre (múltiplas)

Cliente
[ Buscar por nome ou telefone...                 ] [+ Novo]
[ Cliente avulso / Não identificado ]

Referência / mesa (quando aplicável)
[ Ex.: Mesa 04, Balcão, Pedido 33                  ]

[ + Adicionar observação ]

                       [Cancelar] [Abrir comanda]
```

Após seleção de cliente, substituir a linha de opção avulsa pela ficha compacta do cliente selecionado, com nome, telefone e ação clara para trocar/remover. Apresentar uma única pessoa selecionada.

## Fases de execução

### Fase 0 — Validar regras e dados

- Confirmar regras por `SaleCategory`: tipo, unicidade, categoria inativa e quando referência/cliente são necessários ou opcionais.
- Verificar quantidade típica e máxima de clientes enviados em `SaleController@index`, impacto no tempo/peso da página e se busca deve ser local ou remota.
- Confirmar comportamento esperado ao mudar categoria após preencher cliente/referência.
- Validar o fluxo em desktop e viewport estreita antes de decidir entre modal responsivo e tela dedicada. A hipótese inicial é manter modal desktop e usar apresentação quase em tela cheia em mobile.

### Fase 1 — Busca e seleção rápida

- Criar/reutilizar componente de seleção pesquisável conforme os padrões existentes do projeto.
- Implementar teclado, estados visuais, acessibilidade e correspondência por nome/telefone.
- Preservar cliente avulso e integração com cadastro inline atual.
- Critério da fase: usuário seleciona cliente sem rolar uma lista longa; cliente criado inline retorna selecionado e foco utilizável.

### Fase 2 — Formulário progressivo por categoria

- Reordenar os campos pela sequência categoria → pessoa → referência → nota opcional.
- Exibir regra da categoria em texto compacto e contextual, deixando claro quando uma referência é recomendada/necessária.
- Reduzir ruído visual das observações e campos não pertinentes; preservar dados ao alternar categoria.
- Rever altura, rolagem, footer, foco inicial e foco ao fechar no dialog.

### Fase 3 — Estados, responsividade e acabamento

- Desenhar e implementar os estados de validação, envio, falha e sucesso sem perder os dados digitados.
- Em mobile: controles com área de toque adequada, resultados sem overflow e ação principal sempre acessível.
- Revisar contraste, foco visível, nomes acessíveis, leitores de tela e navegação só por teclado.
- Usar feedback breve de sucesso e levar a pessoa ao detalhe da comanda criada, conforme comportamento atual.

### Fase 4 — Validação operacional

- Fazer revisão com operadores que abrem comandas em contextos distintos (barbearia/salão e mesa/balcão).
- Comparar o tempo entre abrir modal e iniciar nova comanda, taxa de busca sem resultado, erros de associação de cliente e uso do caminho avulso.
- Ajustar rótulos e defaults a partir da operação observada; não inferir uma categoria padrão sem evidência.

## Critérios de aceite

- A pessoa pode encontrar cliente por parte do nome ou telefone sem navegar pela lista inteira.
- A opção avulsa nunca fica escondida por causa da busca ou de uma lista vazia.
- Resultados distinguem homônimos usando telefone quando disponível; não selecionam automaticamente uma correspondência ambígua.
- Cadastro rápido mantém o contexto e retorna com o novo cliente selecionado.
- A categoria permanece necessária quando não houver default inequívoco, e seu tipo/regra continuam visíveis sem competir com a tarefa.
- Campos opcionais são reconhecíveis como opcionais; observação não ocupa o espaço principal no estado inicial.
- Referência preenchida não é perdida ao alternar categoria.
- É possível concluir, cancelar, pesquisar, selecionar, limpar/trocar cliente e usar cadastro rápido apenas com teclado.
- Foco inicial, ordem de tabulação, anúncio de resultados, erros e retorno de foco do diálogo funcionam com tecnologia assistiva.
- Em telas estreitas, conteúdo não é cortado, resultados não ficam atrás do footer e a ação principal continua alcançável.
- Submissão repetida não cria comandas duplicadas; erros do servidor preservam os dados para correção.

## Indicadores recomendados

- Mediana do tempo da abertura do modal até a criação da comanda.
- Percentual de abertura com cliente identificado versus avulso, segmentado por categoria.
- Percentual de buscas sem resultado e tempo até selecionar cliente.
- Frequência de criação rápida, abandono do modal e erros de validação.
- Incidentes de cliente associado incorretamente reportados pela operação.

## Fora de escopo nesta proposta

- Redesenho da página inteira de comandas ou do detalhe/checkout.
- Mudanças nas regras de faturamento, unicidade, permissões, estoque ou pagamentos.
- Cadastro completo de serviços, produtos ou profissionais no modal inicial.
- Escolha automática de categoria, pessoa ou referência sem confirmação.

## Questões para resolver durante a Fase 0

- Qual categoria deve ser sugerida, se alguma, para cada unidade?
- Em quais categorias referência deve ser destacada ou validada como necessária?
- Quantos clientes compõem a lista enviada hoje, e qual o limite em que busca local deixa de ser adequada?
- Operadores precisam abrir a comanda e já lançar o primeiro item sem passar pela tela de detalhe?
- Quais campos mínimos de cliente são aceitos na operação real ao usar cadastro rápido?
