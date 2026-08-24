# EV-001 — Shell e agenda desktop

- **Data:** 24/08/2026
- **Ambiente:** aplicação autenticada com aparência de conta operacional
- **Papel:** desconhecido
- **Viewport:** 1710 × 929 CSS px
- **Rota principal:** `/calendar`
- **Mutação:** nenhuma
- **Captura:** DOM acessível, labels, controles, estados ARIA, estilos computados e URLs após navegação explícita
- **Redação:** nomes de pessoas, registros e identificadores operacionais não foram preservados

## Interações observadas

1. expansão do grupo Principal na sidebar;
2. navegação para Agenda;
3. abertura e fechamento do menu global Novo;
4. abertura do diálogo Novo agendamento;
5. abertura de seletores não sensíveis sem alterar seleção;
6. cancelamento do diálogo sem salvar;
7. abertura dos menus Visualização e Ações;
8. abertura e fechamento do filtro;
9. abertura das abas Geral, Visualização e Cores das configurações da agenda;
10. fechamento sem modificar configurações.

## Garantia de não persistência

- Nenhum campo foi preenchido.
- Nenhum switch foi alterado.
- Nenhuma opção foi selecionada.
- `Salvar`, `Criar comanda`, `Criar`, `Confirmar`, `Enviar`, `Contratar` e equivalentes não foram acionados.
- O batch terminou na rota `/calendar`, sem diálogo visível.
