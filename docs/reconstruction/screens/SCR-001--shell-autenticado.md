# SCR-001 — Shell autenticado

## Contexto

- **Rota:** transversal às páginas autenticadas
- **Papel:** desconhecido
- **Viewport:** 1710 × 929
- **Evidência:** EV-001; UX-001, UX-002, UX-004, UX-005

## Objetivo

Orientar o usuário entre domínios operacionais e oferecer ações globais sem perder o contexto da página.

## Hierarquia

1. sidebar persistente com identidade do usuário e criação rápida;
2. menu hierárquico por domínio;
3. header contextual da página;
4. conteúdo principal;
5. ajuda/chat flutuante.

## Componentes observados

- avatar/perfil e saudação;
- badges de notificação/mensagem;
- botão global Novo com dropdown categorizado;
- menu vertical com item, ícone, badge `novo`, expansão, seleção e recolhimento;
- versão do app no rodapé da sidebar;
- header por página;
- suporte/chat flutuante.

## Estados

| Estado | Resultado | Evidência |
|---|---|---|
| grupo recolhido | somente título do domínio | EV-001 |
| grupo expandido | páginas-filhas abaixo do grupo | EV-001 |
| página ativa | item selecionado e ancestral indicado | EV-001 |
| menu Novo aberto | atalhos agrupados por domínio | EV-001 |

## Acessibilidade observada

- menus expõem `role=menuitem` e `aria-expanded` nos grupos;
- badges e diversos ícones dependem de nomes acessíveis incompletos;
- o shell usa `aside` e a página usa `header`, mas não foi observado um landmark `main` no Batch 1;
- alguns controles somente com ícone não possuem nome acessível útil.

## Tratamento independente proposto

- sidebar com landmarks e navegação nomeada;
- command palette pesquisável para criação rápida;
- ícones sempre acompanhados de `aria-label`/tooltip;
- foco devolvido ao gatilho após fechar dropdown/modal;
- navegação compacta em telas menores com preservação do contexto.

## Critérios de aceitação

- Dado um grupo recolhido, quando o usuário o ativa por clique ou teclado, então suas páginas ficam visíveis e `aria-expanded=true`.
- Dada uma rota ativa, então item e grupo ancestral comunicam seleção visual e semanticamente.
- Dado o menu de criação aberto, então Escape o fecha e devolve foco ao gatilho.
- Nenhuma ação global indisponível ao papel deve ser executável apenas por conhecer a rota.

## Complemento mobile/tablet

O Batch EV-008 observou substituição da sidebar por barra inferior flutuante em 390 e 768 px, com ações variáveis por rota e chat acima dela. A especificação transversal, riscos e proposta independente estão em `SCR-013--experiencia-mobile-tablet.md`.

## Desconhecidos

- comportamento em aparelho físico, landscape, safe area e teclado virtual;
- persistência do estado expandido;
- ordem real de foco;
- conteúdo e permissões condicionais por papel/plano.
