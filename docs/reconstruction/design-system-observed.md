# Design system observado

## Classificação

Valores abaixo são **Observed** ou **Inferred** a partir do render; não são especificação interna do produto.

| Categoria | Observação | Confiança | Evidência |
|---|---|---|---|
| Tipografia | Inter com fallbacks de sistema | alta | UX-004 |
| Primária | azul-violeta próximo de `#505afb` | média-alta | UX-004 |
| Neutro escuro | sidebar grafite | alta | EV-001 |
| Superfície | branco/cinza muito claro | alta | EV-001 |
| Raios | recorrência aproximada de 6 e 12 px | média | UX-004 |
| Densidade | compacta em header e agenda | alta | EV-001 |
| Biblioteca | classes e padrões Ant Design | alta, inferida | UX-005 |
| Ícones | ícones lineares de ações/menus | alta | EV-001 |

## Linguagem funcional

- CTA primário saturado e compacto;
- ações secundárias em botões outlined claros;
- sidebar escura como âncora de navegação;
- status comunicados por cor + texto em muitos pontos;
- dialogs largos para fluxos operacionais densos;
- tabelas/grades de alta densidade informacional.

## Limite clean-room

O produto novo deve derivar tokens próprios e uma composição independente. Logo, ilustrações, textos e combinação distintiva de marca não serão reproduzidos.
# Complemento — marketing público (EV-009)

- fundo predominantemente branco e grandes áreas de respiro;
- Inter com H1 próximo de 64/76,8 px no desktop e 36/43,2 px no mobile;
- H2 próximo de 48/57,6 px no desktop e 32/38,4 px no mobile;
- azul-violeta recorrente próximo de `#505afb`;
- raio recorrente próximo de 12 px em CTAs/cards;
- header mobile fixo de aproximadamente 72 px;
- CTA de teste flutuante observado em desktop e mobile;
- seções longas com screenshots amplos, cards, prova social, carrosséis e CTAs repetidos.

Valores observados não são tokens propostos. A identidade independente continua definida em `design-system-proposed.md`.
