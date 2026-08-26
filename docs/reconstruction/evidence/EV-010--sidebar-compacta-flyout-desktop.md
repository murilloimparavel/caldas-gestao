# EV-010 — Sidebar compacta e flyout desktop

**Data:** 26/08/2026
**Fonte:** inspeção autorizada em Chrome no shell autenticado do Belasis e smoke local do Caldas
**Classificação:** observação funcional + validação de implementação independente

## Escopo

Foi estudado o comportamento da navegação lateral em dois estados: expandido e recolhido. Não foram coletados cookies, credenciais, armazenamento local ou APIs privadas. Rótulos pessoais visíveis durante a sessão não são reproduzidos neste registro.

## Observado na referência

- grupos de navegação funcionam como accordions independentes;
- mais de um grupo pode ficar aberto no estado expandido;
- ao recolher, a lateral mantém ícones de categorias;
- acionar uma categoria abre um menu flutuante à direita com título e itens filhos;
- o menu flutuante é separado da largura do rail;
- a navegação por um item leva à rota correspondente e encerra o contexto do flyout.

## Implementado no Caldas

- grupos autorizados: `Principal`, `Cadastros`, `Controle`, `Configurações` e `Financeiro`;
- rail de ícones no desktop recolhido;
- flyout Radix com links Inertia/Wayfinder;
- filtros de permissão preservados antes da renderização;
- categoria ativa destacada pela URL;
- `calendar.view` como permissão canônica da Agenda;
- accordion visualmente distinto dos filhos;
- barra inferior e Sheet mobile preservados.

## Validação local

No dashboard local, o smoke manual confirmou:

1. vários grupos abertos simultaneamente;
2. rail com apenas os ícones das categorias;
3. flyout Financeiro com Painel Financeiro, Contas a Pagar/Receber e Comissões;
4. fechamento por `Escape`;
5. navegação para `/finance/commissions` pelo flyout;
6. ausência de erros de aplicação nos logs recentes do navegador.

## Limites

O projeto não possui suíte frontend/browser automatizada configurada. A validação de viewport mobile físico, permissões parciais e leitores de tela permanece pendente para o próximo passe de QA.
