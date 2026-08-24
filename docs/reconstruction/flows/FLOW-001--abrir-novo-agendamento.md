# FLOW-001 — Abrir e abandonar novo agendamento

## Resultado do usuário

Iniciar a composição de um agendamento e sair sem persistir alterações.

## Ator e precondições

- usuário autenticado com acesso à agenda;
- papel formal desconhecido;
- agenda carregada em visualização semanal.

## Passos observados

1. Usuário aciona Novo no header da agenda.
2. Sistema abre diálogo Novo agendamento.
3. Sistema pré-preenche data, status, profissional/contexto, horário, duração, lembrete e não recorrência.
4. Usuário pode abrir seletores sem persistência aparente.
5. Usuário aciona Cancelar.
6. Diálogo fecha, rota e agenda permanecem.

## Transição

```text
agenda_visível → composição_local → abandonada
```

Não houve evidência de entidade persistida. A ausência de chamada de criação não foi confirmada por captura de rede; portanto “composição local” é **Inferred**.

## Regras

| ID | Classificação | Regra | Evidência | Confiança |
|---|---|---|---|---|
| RULE-001 | Observed | Novo abre um diálogo sem sair de `/calendar`. | EV-001 | alta |
| RULE-002 | Observed | Cancelar fecha o diálogo sem mudança visível na agenda. | EV-001 | alta |
| RULE-003 | Observed | Lembrete inicia ligado e encaixe desligado no contexto testado. | EV-001 | alta para este contexto |
| RULE-004 | Inferred | Defaults podem vir de configurações globais da agenda. | EV-001 | média |
| RULE-005 | Unknown | Cancelar não cria rascunho no servidor. | sem rede | baixa |

## Given/When/Then

- Dada a agenda carregada, quando Novo é acionado, então um formulário acessível abre sem navegação de rota.
- Dado um formulário sem alterações, quando Cancelar é acionado, então ele fecha sem novo item visível.
- Dado um diálogo aberto, quando Escape/Fechar é acionado, então o foco deve retornar ao gatilho e nenhum efeito externo deve ocorrer.

## Testes não realizados

- Salvar e Criar comanda;
- validações obrigatórias;
- busca e criação de cliente;
- conflito e encaixe;
- múltiplos itens;
- recorrência;
- lembrete real;
- contratos de rede.
